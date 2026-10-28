<?php
$mostrarNuevaClave = isset($_GET['token']) && is_string($_GET['token']) && trim($_GET['token']) !== '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIGHO | Recuperar contraseña</title>
<link rel="icon" type="image/png" href="escudo_jjfh.png">
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;min-height:100dvh;padding:12px 12px 28px;display:flex;align-items:flex-start;justify-content:center;overflow-x:hidden;background:linear-gradient(140deg,#eef5fa,#f8fbfd);font-family:"Segoe UI",Arial,sans-serif;color:#243b53}.card{width:min(520px,100%);margin:clamp(8px,4vh,36px) auto 0;background:#fff;border:1px solid #dbe7ef;border-radius:22px;overflow:hidden;box-shadow:0 18px 50px rgba(8,43,76,.13);flex:0 0 auto}.stripe{height:7px;background:linear-gradient(90deg,#082b4c,#1769aa,#2fa45a)}.head{display:flex;align-items:center;gap:16px;padding:25px 28px 10px}.head img{width:64px;height:64px;object-fit:contain}.head span{display:block;font-size:11px;font-weight:800;letter-spacing:1.5px;color:#2fa45a}.head h1{margin:4px 0 0;font-size:24px;color:#0d3f70}.body{padding:12px 28px 28px}.intro{font-size:14px;line-height:1.65;color:#5b7181;margin:0 0 18px}.form-group{margin:16px 0}.form-group label{display:block;font-size:13px;font-weight:700;color:#274b62;margin-bottom:7px}.form-group input{width:100%;min-height:46px;padding:12px 13px;border:1px solid #cbdce5;border-radius:11px;font-size:15px;color:#243b53;background:#fff}.form-group input:focus{outline:3px solid rgba(23,105,170,.12);border-color:#1769aa}.help{font-size:12px;color:#6b8091;line-height:1.5}.button{width:100%;min-height:52px;border:0;border-radius:11px;padding:12px 15px;background:linear-gradient(135deg,#0d3f70,#1769aa);color:#fff;font-weight:800;font-size:15px;line-height:1.3;white-space:normal;overflow-wrap:anywhere;cursor:pointer;margin-top:8px}.button:disabled{opacity:.6;cursor:wait}.back{display:block;margin:18px auto 0;color:#1769aa;font-weight:700;text-decoration:none;text-align:center}.status{display:block;visibility:hidden;min-height:50px;margin:16px 0 0;padding:12px 14px;border-radius:10px;background:#edf8f0;border:1px solid #b9e3c5;color:#205d37;font-size:13px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere}.status.error{background:#fff0f0;border-color:#efb3b3;color:#a52d2d}.status.visible{visibility:visible}.hidden{display:none!important}@media(max-width:500px){body{padding:10px 10px 24px}.card{margin:8px auto 0;border-radius:18px}.head{padding:22px 20px 8px;align-items:center}.body{padding:12px 20px 24px}.head img{width:52px;height:52px;flex:0 0 52px}.head h1{font-size:21px;line-height:1.25}.intro{margin-bottom:16px}.form-group{margin:14px 0}.status{min-height:54px}}
.email-spam-notice{margin:0 0 18px;padding:12px 14px;border:1px solid #b8d7ee;border-radius:11px;background:#eef7fd;color:#28516d;font-size:13px;line-height:1.55}.email-spam-notice strong{color:#0d3f70}
</style>
</head>
<body>
<main class="card">
<div class="stripe"></div>
<header class="head"><img src="escudo_jjfh.png" alt="Escudo institucional"><div><span>SIGHO · JJFH</span><h1 id="titulo"><?= $mostrarNuevaClave ? 'Crear nueva contraseña' : 'Recuperar contraseña' ?></h1></div></header>
<div class="body">
<p class="intro" id="intro"><?= $mostrarNuevaClave ? 'Elige una contraseña segura. Por seguridad, el enlace vence en 30 minutos y solo se puede usar una vez.' : 'Escribe el correo asociado a tu cuenta. Si está registrado, recibirás un enlace para crear una contraseña nueva.' ?></p>
<div class="email-spam-notice" role="note"><strong>¿No ves el correo?</strong> Puede tardar unos minutos. Revisa también las carpetas <strong>Spam</strong> o <strong>Correo no deseado</strong>.</div>
<form id="formSolicitud" class="<?= $mostrarNuevaClave ? 'hidden' : '' ?>">
<div class="form-group"><label for="correo">Correo electrónico</label><input id="correo" name="correo" type="email" autocomplete="email" required maxlength="254" placeholder="tu-correo@ejemplo.com"></div>
<button class="button" id="btnEnviarRecuperacion" type="submit">Enviar enlace de recuperación</button>
</form>
<form id="formNuevaClave" class="<?= $mostrarNuevaClave ? '' : 'hidden' ?>">
<div class="form-group"><label for="nuevaClave">Nueva contraseña</label><input id="nuevaClave" name="nuevaClave" type="password" autocomplete="new-password" required minlength="8" maxlength="128" placeholder="Mínimo 8 caracteres"><p class="help">Debe incluir mayúscula, minúscula y número; no debe contener espacios.</p></div>
<div class="form-group"><label for="confirmarClave">Confirmar contraseña</label><input id="confirmarClave" name="confirmarClave" type="password" autocomplete="new-password" required minlength="8" maxlength="128" placeholder="Escribe la contraseña otra vez"></div>
<button class="button" type="submit">Guardar nueva contraseña</button>
</form>
<div id="estado" class="status" role="status" aria-live="polite"></div>
<a class="back" href="index.html?abrirLogin=1">← Volver al inicio de sesión</a>
</div>
</main>
<script>
(() => {
  const token = new URLSearchParams(window.location.search).get('token') || '';
  const formSolicitud = document.getElementById('formSolicitud');
  const formNuevaClave = document.getElementById('formNuevaClave');
  const estado = document.getElementById('estado');
  const titulo = document.getElementById('titulo');
  const intro = document.getElementById('intro');
  const botonSolicitud = document.getElementById('btnEnviarRecuperacion');
  let cooldownHasta = 0;
  let timerCooldown = null;
  function iniciarCooldown(segundos = 60) {
    cooldownHasta = Date.now() + segundos * 1000;
    if (timerCooldown) clearInterval(timerCooldown);
    const actualizar = () => {
      const restantes = Math.max(0, Math.ceil((cooldownHasta - Date.now()) / 1000));
      if (botonSolicitud) {
        botonSolicitud.disabled = restantes > 0;
        botonSolicitud.textContent = restantes > 0 ? `Espera ${restantes} s para reenviar` : 'Enviar enlace de recuperación';
      }
      if (restantes <= 0) {
        clearInterval(timerCooldown);
        timerCooldown = null;
      }
    };
    actualizar();
    timerCooldown = setInterval(actualizar, 250);
  }
  if (token) {
    formSolicitud.classList.add('hidden');
    formNuevaClave.classList.remove('hidden');
    titulo.textContent = 'Crear nueva contraseña';
    intro.textContent = 'Elige una contraseña segura. Por seguridad, el enlace vence en 30 minutos y solo se puede usar una vez.';
  }
  function mostrarEstado(mensaje, error = false) {
    estado.textContent = mensaje;
    estado.classList.add('visible');
    estado.classList.toggle('error', error);
  }
  async function enviarAccion(accion, datos, boton) {
    const original = boton.textContent;
    boton.disabled = true;
    boton.textContent = 'Procesando…';
    estado.classList.remove('visible', 'error');
    try {
      const response = await fetch(`api/api.php?action=${encodeURIComponent(accion)}`, {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify(datos)
      });
      const raw = await response.text();
      let data;
      try { data = JSON.parse(raw); } catch (_) { throw new Error('El servidor respondió con un formato inesperado. Verifica Apache, PHP y la conexión con MySQL.'); }
      if (!response.ok || data.ok === false) throw new Error(data.message || 'No fue posible completar la solicitud.');
      return data;
    } finally {
      if (boton === botonSolicitud && Date.now() < cooldownHasta) {
        boton.disabled = true;
      } else {
        boton.disabled = false;
        boton.textContent = original;
      }
    }
  }
  formSolicitud.addEventListener('submit', async event => {
    event.preventDefault();
    if (Date.now() < cooldownHasta) {
      mostrarEstado('Debes esperar 1 minuto para volver a enviar el enlace', true);
      return;
    }
    const boton = formSolicitud.querySelector('button[type="submit"]');
    // Iniciar el bloqueo antes de llamar al servidor para impedir clics múltiples concurrentes.
    iniciarCooldown(60);
    try {
      const data = await enviarAccion('request_password_reset', {correo:document.getElementById('correo').value.trim()}, boton);
      mostrarEstado(data.message || 'Si el correo corresponde a una cuenta activa de SIGHO, recibirás instrucciones para recuperar la contraseña.');
    } catch (error) { mostrarEstado(error.message || 'No fue posible procesar la solicitud.', true); }
  });
  formNuevaClave.addEventListener('submit', async event => {
    event.preventDefault();
    const nuevaClave = document.getElementById('nuevaClave').value;
    const confirmarClave = document.getElementById('confirmarClave').value;
    const boton = formNuevaClave.querySelector('button[type="submit"]');
    if (nuevaClave !== confirmarClave) { mostrarEstado('La confirmación de la contraseña no coincide.', true); return; }
    if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)\S{8,}$/.test(nuevaClave)) { mostrarEstado('La contraseña debe tener mínimo 8 caracteres, mayúscula, minúscula y número, sin espacios.', true); return; }
    try {
      const data = await enviarAccion('reset_password', {token, nuevaClave, confirmarClave}, boton);
      formNuevaClave.classList.add('hidden');
      mostrarEstado(data.message || 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
    } catch (error) { mostrarEstado(error.message || 'No fue posible cambiar la contraseña.', true); }
  });
})();
</script>
</body>
</html>
