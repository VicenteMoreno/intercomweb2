# Intercom Web (instalación completa)

Este repositorio contiene una implementación de señalización WebSocket en PHP (Ratchet) y cliente WebRTC para intercom P2P en LAN.
Sigue las instrucciones de instalación para desplegarlo en tu servidor Apache con HTTPS.

Resumen:
- Señalización: signaling_server.php (Ratchet)
- Cliente: audio.js + index.php
- Configuración: config.txt (usuarios por IP y salas)
- Presencia: presence.php escribe pings por IP en /presence/

Instalación y despliegue: ver README más abajo (comandos).