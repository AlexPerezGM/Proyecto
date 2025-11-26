document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("loginForm");

    if (!form) {
        console.error('Formulario de login no encontrado (id="loginForm").');
        return;
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        const datos = new FormData(form);

        try {
            const base = (window.APP_BASE || '/');
            const apiUrl = base + 'api/InicioSesion.php';

            const res = await fetch(apiUrl, {
                method: "POST",
                body: datos
            });

            const data = await res.json();

            if (data.ok) {
                // servidor puede devolver 'redirect' relativo; normalizamos
                let redirectTo = data.redirect || 'views/dashboard.php';
                // eliminar prefijos ../ o ./
                redirectTo = redirectTo.replace(/^(\.\.\/|\.\/)+/, '');
                if (!redirectTo.match(/^https?:\/\//) && !redirectTo.startsWith('/')) {
                    redirectTo = base + redirectTo;
                }
                window.location.href = redirectTo;
            } else {
                // soportar tanto 'msg' como 'mensaje' según backend
                alert(data.msg || data.mensaje || "Usuario o contraseña incorrectos");
            }
        } catch (err) {
            console.error("Error al iniciar sesión:", err);
            alert("Error al conectar con el servidor");
        }
    });
});
