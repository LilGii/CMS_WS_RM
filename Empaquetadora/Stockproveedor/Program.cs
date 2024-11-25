var builder = WebApplication.CreateBuilder(args);


builder.Services.AddControllers();
builder.Services.AddHttpClient(); 

var app = builder.Build();


app.UseAuthorization();
app.MapControllers();

app.Run();
