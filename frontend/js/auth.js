// frontend/js/auth.js

// =====================
// LOGIN FUNCTION
// =====================
async function login() {
  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value.trim();

  if (!email || !password) {
    alert("Please enter email and password");
    return;
  }

  const result = await apiRequest("/auth/login", "POST", { email, password });

  if (result.status === "success") {
    localStorage.setItem("token", result.data.token);
    localStorage.setItem("user", JSON.stringify(result.data.user));

    alert("Login successful");
    window.location.href = "dashboard.html";
  } else {
    alert(result.message);
  }
}

// =====================
// REGISTER FUNCTION
// =====================
async function register() {
  const name = document.getElementById("name").value.trim();
  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value.trim();
  const role = document.getElementById("role").value;

  if (!name || !email || !password || !role) {
    alert("All fields are required");
    return;
  }

  const result = await apiRequest("/auth/register", "POST", {
    name,
    email,
    password,
    role
  });

  if (result.status === "success") {
    alert("Registration successful. Please login.");
    window.location.href = "login.html";
  } else {
    alert(result.message);
  }
}

// =====================
// LOGOUT FUNCTION
// =====================
function logout() {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  window.location.href = "index.html";
}
