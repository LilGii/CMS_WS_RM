from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
from datetime import datetime

app = Flask(__name__)
CORS(app)  # Habilitar CORS

# URLs de las tablas en Firebase
firebase_base_url = "https://almacen-bb9c0-default-rtdb.firebaseio.com"
almacenproductos_url = f"{firebase_base_url}/Almacenproductos.json"
devoluciones_url = f"{firebase_base_url}/devoluciones.json"

# Función para obtener el siguiente ID de devolución
def obtener_siguiente_id_devolucion():
    try:
        respuesta = requests.get(devoluciones_url)
        if respuesta.status_code == 200:
            devoluciones = respuesta.json()
            if devoluciones:
                ultimo_id = max(
                    [int(key.replace("DEV", "")) for key in devoluciones.keys() if key.startswith("DEV")]
                )
                return f"DEV{ultimo_id + 1}"
            else:
                return "DEV1"
        else:
            print(f"Error al obtener devoluciones: {respuesta.status_code}, {respuesta.text}")
            return None
    except Exception as e:
        print(f"Error al obtener el siguiente ID de devolución: {e}")
        return None

# Función para validar múltiples productos
def validar_productos_y_cantidades(productos):
    try:
        respuesta = requests.get(almacenproductos_url)
        if respuesta.status_code != 200:
            return "Error al conectar con Almacenproductos."

        almacenproductos = respuesta.json()
        errores = []

        for producto, cantidad in productos.items():
            producto_lower = producto.lower()
            for key, value in almacenproductos.items():
                if key.lower() == producto_lower:
                    if value < cantidad:
                        errores.append(
                            f"Cantidad insuficiente para '{producto}': Disponible {value}, solicitado {cantidad}."
                        )
                    break
            else:
                errores.append(f"El producto '{producto}' no existe en Almacenproductos.")

        if errores:
            return errores
        return True
    except Exception as e:
        print(f"Error al validar productos y cantidades: {e}")
        return "Error interno al validar productos y cantidades."

# Endpoint para agregar devoluciones de múltiples productos
@app.route('/agregar_devoluciones', methods=['POST'])
def agregar_devoluciones():
    try:
        data = request.get_json()
        print(f"Datos recibidos: {data}")  # Debugging

        # Validar que el cuerpo de la solicitud contiene los datos necesarios
        if not data or not isinstance(data, dict):
            return jsonify({"error": "Se requiere un diccionario con los productos y sus cantidades."}), 400

        # Convertir los productos para que tengan la primera letra mayúscula
        productos = {producto.strip().capitalize(): cantidad for producto, cantidad in data.items()}

        # Validar que los productos existen en Almacenproductos y las cantidades son suficientes
        validacion = validar_productos_y_cantidades(productos)
        if validacion is not True:
            return jsonify({"error": validacion}), 400

        # Obtener el siguiente ID de devolución
        devolucion_id = obtener_siguiente_id_devolucion()
        if devolucion_id is None:
            return jsonify({"error": "Error al generar el ID de la devolución."}), 500

        # Restar las cantidades de los productos en Almacenproductos
        try:
            respuesta = requests.get(almacenproductos_url)
            if respuesta.status_code != 200:
                return jsonify({"error": "Error al conectar con Almacenproductos para actualización."}), 500

            almacenproductos = respuesta.json()
            for producto, cantidad in productos.items():
                producto_lower = producto.lower()
                for key, value in almacenproductos.items():
                    if key.lower() == producto_lower:
                        almacenproductos[key] = value - cantidad
                        break

            # Actualizar Almacenproductos en Firebase
            respuesta_actualizacion = requests.patch(almacenproductos_url, json=almacenproductos)
            print(f"Respuesta de Firebase al actualizar Almacenproductos: {respuesta_actualizacion.status_code}, {respuesta_actualizacion.text}")
            
            if respuesta_actualizacion.status_code != 200:
                return jsonify({"error": "Error al actualizar Almacenproductos."}), 500

        except Exception as e:
            print(f"Error al actualizar Almacenproductos: {e}")
            return jsonify({"error": "Error interno al actualizar el inventario."}), 500

        # Crear los datos para la devolución
        devolucion_data = {
            devolucion_id: {
                **productos,
                "estado": "activo",
                "fecha": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            }
        }

        # Enviar la devolución a Firebase
        respuesta = requests.patch(devoluciones_url, json=devolucion_data)
        print(f"Respuesta de Firebase al agregar devolución: {respuesta.status_code}, {respuesta.text}")  # Debugging

        if respuesta.status_code == 200:
            return jsonify({"message": f"Devolución {devolucion_id} creada exitosamente.", "productos": productos}), 201
        else:
            return jsonify({"error": "Error al agregar la devolución en Firebase."}), 500
    except Exception as e:
        print(f"Error al procesar la devolución: {e}")
        return jsonify({"error": "Error interno al procesar la devolución."}), 500


if __name__ == '__main__':
    app.run(debug=True)
