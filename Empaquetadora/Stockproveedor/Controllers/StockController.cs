using Microsoft.AspNetCore.Mvc;
using Microsoft.Extensions.Configuration;
using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace Stockproveedor.Controllers
{
    [Route("api/[controller]")]
    [ApiController]
    public class StockController : ControllerBase
    {
        private readonly HttpClient _httpClient;
        private readonly string _authSecret;
        private readonly string _basePath;
        private const int MaxQuantity = 1000000000;

        public StockController(IHttpClientFactory httpClientFactory, IConfiguration configuration)
        {
            _httpClient = httpClientFactory.CreateClient();
            _authSecret = configuration["Firebase:AuthSecret"];
            _basePath = configuration["Firebase:BasePath"];

            if (string.IsNullOrEmpty(_authSecret) || string.IsNullOrEmpty(_basePath))
                throw new Exception("La configuración de Firebase no se cargó correctamente.");
        }

        // Endpoint para crear uno o más productos
        [HttpPost]
        public async Task<IActionResult> CreateStock([FromBody] List<StockRequest> requests)
        {
            if (requests == null || requests.Count == 0)
                return BadRequest("La solicitud debe contener al menos un producto.");

            HttpResponseMessage existsResponse = await _httpClient.GetAsync($"{_basePath}Stockproveedor.json?auth={_authSecret}");
            if (!existsResponse.IsSuccessStatusCode)
                return StatusCode((int)existsResponse.StatusCode, "Error al verificar los productos.");

            string responseBody = await existsResponse.Content.ReadAsStringAsync();
            var existingProducts = JsonSerializer.Deserialize<Dictionary<string, object>>(responseBody) ?? new Dictionary<string, object>();

            var results = new List<string>();

            foreach (var request in requests)
            {
                if (string.IsNullOrEmpty(request.Producto) || request.Cantidad <= 0 || request.Cantidad > MaxQuantity)
                {
                    results.Add($"Error: El producto '{request.Producto}' tiene datos inválidos o excede la cantidad máxima de {MaxQuantity}.");
                    continue;
                }

                string producto = char.ToUpper(request.Producto[0]) + request.Producto.Substring(1).ToLower();

                if (existingProducts.ContainsKey(producto))
                {
                    results.Add($"El producto '{producto}' ya existe en Stockproveedor. Si desea actualizar la cantidad, utilice el método PUT.");
                    continue;
                }

                string url = $"{_basePath}Stockproveedor/{producto}.json?auth={_authSecret}";
                var content = new StringContent(request.Cantidad.ToString(), Encoding.UTF8, "application/json");
                HttpResponseMessage response = await _httpClient.PutAsync(url, content);

                if (!response.IsSuccessStatusCode)
                {
                    results.Add($"Error al agregar el producto '{producto}'.");
                }
                else
                {
                    results.Add($"Producto '{producto}' con cantidad {request.Cantidad} agregado a Stockproveedor.");
                }
            }

            return Ok(results);
        }

        // Endpoint para actualizar un producto
        [HttpPut]
        public async Task<IActionResult> UpdateStock([FromBody] StockRequest request)
        {
            if (string.IsNullOrEmpty(request.Producto) || request.Cantidad <= 0 || request.Cantidad > MaxQuantity)
                return BadRequest($"Datos inválidos o la cantidad excede el máximo permitido ({MaxQuantity}).");

            string producto = char.ToUpper(request.Producto[0]) + request.Producto.Substring(1).ToLower();
            string url = $"{_basePath}Stockproveedor/{producto}.json?auth={_authSecret}";

            HttpResponseMessage existsResponse = await _httpClient.GetAsync($"{_basePath}Stockproveedor.json?auth={_authSecret}");
            if (!existsResponse.IsSuccessStatusCode)
                return StatusCode((int)existsResponse.StatusCode, "Error al verificar el producto.");

            string responseBody = await existsResponse.Content.ReadAsStringAsync();
            if (!responseBody.Contains($"\"{producto}\""))
                return NotFound($"El producto '{producto}' no existe en Stockproveedor.");

            var content = new StringContent(request.Cantidad.ToString(), Encoding.UTF8, "application/json");
            HttpResponseMessage response = await _httpClient.PutAsync(url, content);

            if (!response.IsSuccessStatusCode)
                return StatusCode((int)response.StatusCode, "Error al actualizar el producto.");

            return Ok($"Producto '{producto}' actualizado a cantidad {request.Cantidad}.");
        }

        // Endpoint para eliminar un producto
        [HttpDelete("{producto}")]
        public async Task<IActionResult> DeleteStock(string producto)
        {
            if (string.IsNullOrEmpty(producto))
                return BadRequest("El nombre del producto no puede estar vacío.");

            producto = char.ToUpper(producto[0]) + producto.Substring(1).ToLower();
            string url = $"{_basePath}Stockproveedor/{producto}.json?auth={_authSecret}";

            HttpResponseMessage existsResponse = await _httpClient.GetAsync($"{_basePath}Stockproveedor.json?auth={_authSecret}");
            if (!existsResponse.IsSuccessStatusCode)
                return StatusCode((int)existsResponse.StatusCode, "Error al verificar el producto.");

            string responseBody = await existsResponse.Content.ReadAsStringAsync();
            if (!responseBody.Contains($"\"{producto}\""))
                return NotFound($"El producto '{producto}' no existe en Stockproveedor.");

            HttpResponseMessage response = await _httpClient.DeleteAsync(url);

            if (!response.IsSuccessStatusCode)
                return StatusCode((int)response.StatusCode, "Error al eliminar el producto.");

            return Ok($"Producto '{producto}' eliminado de Stockproveedor.");
        }

        // Endpoint para obtener todo el Stockproveedor
        [HttpGet]
        public async Task<IActionResult> GetAllStock()
        {
            string url = $"{_basePath}Stockproveedor.json?auth={_authSecret}";

            try
            {
                HttpResponseMessage response = await _httpClient.GetAsync(url);

                if (!response.IsSuccessStatusCode)
                    return StatusCode((int)response.StatusCode, "Error al obtener el Stockproveedor.");

                string responseBody = await response.Content.ReadAsStringAsync();

                if (string.IsNullOrEmpty(responseBody) || responseBody == "null")
                    return Ok("No hay productos en Stockproveedor.");

                return Ok(JsonSerializer.Deserialize<Dictionary<string, object>>(responseBody));
            }
            catch (Exception ex)
            {
                return StatusCode(500, $"Error interno del servidor: {ex.Message}");
            }
        }
    }

    public class StockRequest
    {
        public string Producto { get; set; }
        public int Cantidad { get; set; }
    }
}
