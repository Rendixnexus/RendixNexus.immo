// =========================
// CONFIG
// =========================
const API_BASE = ""; // Nginx leitet /api automatisch weiter
const res = await fetch("/api/check-verified.php");
const data = await res.json();

if (!data.verified) {
    window.location.href = "/verify-email";
}



if (data.loggedIn) {
    console.log(data.user.email);
}
let currentLang = "en";
let translations = {};

// =========================
// API REQUEST WRAPPER (WICHTIG)
// =========================
async function apiRequest(url, options = {}) {
    try {
        const res = await fetch(`/api/${url}.php`, {
            method: options.method || "POST",
            headers: {
                "Content-Type": "application/json",
                ...(options.headers || {})
            },
            body: options.body ? JSON.stringify(options.body) : null
        });

        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.message || "API_ERROR");
        }

        return data;
    } catch (err) {
        console.error("API ERROR:", err);
        throw new Error("SERVER_NOT_REACHABLE");
    }
}

// =========================
// LOGIN
// =========================
async function login(email, password) {
    const csrf = localStorage.getItem("csrf_token") || "";

    const res = await fetch("/api/login.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            email,
            password,
            csrf_token: csrf
        })
    });

    const data = await res.json();

    if (!data.success) {
        throw new Error(data.message || "LOGIN_FAILED");
    }

    // CSRF speichern
    if (data.csrf_token) {
        localStorage.setItem("csrf_token", data.csrf_token);
    }

    return data;
}

// =========================
// REGISTER
// =========================
async function register(name, email, password) {
    return await apiRequest("register", {
        body: { name, email, password }
    });
}

// =========================
// LOGIN FORM
// =========================
document.getElementById("login-form")?.addEventListener("submit", async (e) => {
    e.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;

    try {
        await login(email, password);
        window.location.href = "/dashboard";
    } catch (err) {
        alert("Login fehlgeschlagen");
        console.error(err);
    }
});
// =========================
// REGISTER FORM
// =========================
document.getElementById("register-form")?.addEventListener("submit", async (e) => {
    e.preventDefault();

    const name = document.getElementById("name")?.value;
    const email = document.getElementById("email")?.value;
    const password = document.getElementById("password")?.value;

    try {
        await register(name, email, password);
        window.location.href = "/login";
    } catch (err) {
        alert("Registrierung fehlgeschlagen");
    }
});

// =========================
// LANGUAGE SWITCH (optional)
// =========================
document.getElementById("language-select")?.addEventListener("change", (e) => {
    currentLang = e.target.value;
    localStorage.setItem("lang", currentLang);
});

// =========================
// INIT
// =========================
window.addEventListener("DOMContentLoaded", () => {
    const savedLang = localStorage.getItem("lang");
    if (savedLang) currentLang = savedLang;
});

