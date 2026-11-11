// Abrir modal login con el botón 👤
const openLoginBtn = document.getElementById('open-login');
if (openLoginBtn) {
  openLoginBtn.addEventListener('click', function (e) {
    e.preventDefault();
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    loginModal.show();
  });
}
  
  // Tarjetas de postres (ejemplo dinámico)
  const postres = [
    {
      nombre: "Cheesecake de Fresas",
      descripcion: "Cremoso y con fresas frescas de temporada.",
      imagen: "https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=800&q=80"
    },
    {
      nombre: "Brownie de Chocolate",
      descripcion: "Denso, húmedo y con chispas de chocolate.",
      imagen: "https://images.unsplash.com/photo-1600891964599-f61ba0e24092?auto=format&fit=crop&w=800&q=80"
    },
    {
      nombre: "Tiramisú Clásico",
      descripcion: "Un clásico italiano con café y cacao.",
      imagen: "https://images.unsplash.com/photo-1542831371-29b0f74f9713?auto=format&fit=crop&w=800&q=80"
    }
  ];


  let total = 0; // variable global para acumular el total

function agregarCarrito(producto, precio) {
  // Convertir el precio a número (quita el $ y puntos)
  let precioLimpio = parseInt(precio.replace('$', '').replace('.', ''));

  // Sumar al total
  total += precioLimpio;

  // Crear un elemento en el carrito
  let carrito = document.getElementById("carrito");
  let item = document.createElement("p");
  item.textContent = producto + " - " + precio;
  carrito.appendChild(item);

  // Mostrar el total formateado correctamente
  let totalElemento = document.getElementById("total");
  if (!totalElemento) {
    totalElemento = document.createElement("p");
    totalElemento.id = "total";
    carrito.appendChild(totalElemento);
  }
  totalElemento.textContent = "Total: $" + total.toLocaleString('es-CO');
}
  
  