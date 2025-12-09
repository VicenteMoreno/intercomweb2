# Intercom Web (instalación completa)

Este repositorio contiene una implementación de señalización WebSocket en PHP (Ratchet) y cliente WebRTC para intercom P2P en LAN.
Sigue las instrucciones de instalación para desplegarlo en tu servidor Apache con HTTPS.

Resumen:
- Señalización: signaling_server.php (Ratchet)
- Cliente: audio.js + index.php
- Configuración: config.txt (usuarios por IP y salas)
- Presencia: presence.php escribe pings por IP en /presence/

Instalación y despliegue: ver README más abajo (comandos).

===================

Estructura de carpetas (sugerida)

intercom/ ← raíz del proyecto (DocumentRoot o subruta)

index.php

audio.js

utils.php

signaling_server.php

presence.php

config.php

salas.php

composer.json

intercom.service

config.txt ← configuración (ejemplo incluido)

style.css

README.md

backups/ (creado por instalación)

presence/ (creado por instalación)

signals/ (creado por instalación)

vendor/ (creado por composer)

logo.svg, icono.png, altavoz.png, conf.png (poner tus imágenes aquí)

===================

1.-Preparar paquetes y PHP (como root o con sudo)

Actualizar repositorios

sudo apt update

Instalar PHP CLI y extensiones básicas, Apache y tools (ajusta si ya los tienes)

sudo apt install -y apache2 php php-cli php-xml php-mbstring unzip git curl

Instalar composer (si no está)

opción rápida:

curl -sS https://getcomposer.org/installer | php

sudo mv composer.phar /usr/local/bin/composer

sudo chmod +x /usr/local/bin/composer

=======================

2.- Crear la carpeta del proyecto (ruta ejemplo /var/www/html/intercom) y permisos

sudo mkdir -p /var/www/html/intercom

cd /var/www/html/intercom

copia aquí los ficheros que te he dado (index.php, audio.js, utils.php, signaling_server.php, etc.)

Si trabajas localmente, git init / remote push; si subes desde tu máquina, clona y despliega.


Crear directorios necesarios y ajustar permisos

sudo mkdir -p backups presence signals

sudo chown -R www-data:www-data /var/www/html/intercom

sudo chmod -R 755 /var/www/html/intercom

=================================
3.- Instalar dependencias PHP (Ratchet)

cd /var/www/html/intercom

composer install

composer creará vendor/ con Ratchet y sus dependencias

==================================

4.- Configurar Apache para proxy WebSocket (añadir reglas en tu VirtualHost HTTPS)
Edita el VirtualHost que sirve https://10.204.2.5 y añade estas líneas (dentro del bloque <VirtualHost *:443>):

Habilita proxy WebSocket para intercom

ProxyPass "/intercom/ws" "ws://127.0.0.1:8443/"

ProxyPassReverse "/intercom/ws" "ws://127.0.0.1:8443/"

===================================

5.- Crear unit systemd para el servidor de señalización y arrancarlo.

Copia intercom.service en /etc/systemd/system/intercom.service (o crea el fichero con el contenido que te pasé)
sudo cp /var/www/html/intercom/intercom.service /etc/systemd/system/intercom.service

Recargar systemd y arrancar servicio

sudo systemctl daemon-reload
sudo systemctl enable --now intercom
sudo systemctl status intercom

Ver logs:

sudo journalctl -u intercom -f

=============================

Alternativa: arrancar manualmente para debugging:

cd /var/www/html/intercom

php signaling_server.php

si ves "Ratchet escuchando..." ya está activo

====================================

6.- Comprobar despliegue en los clientes.

Asegúrate de que tu VirtualHost HTTPS ya sirve la ruta /intercom (index.php). Si URL base es https://10.204.2.5/intercom/ abre:

https://10.204.2.5/intercom/index.php

En dos equipos conectados en LAN (cada uno debe figurar en config.txt como IP de un botón):

Abrir la URL en Chrome/Firefox con HTTPS.

Hacer clic en "Start" (permitir micrófono).

En la lista de botones verás los peers (en la interfaz original salen botones; el script lista peers automáticamente en background).

Mantén presionado el botón del peer para hablar (presion-to-talk). Deberías oír audio P2P.

=================================

Comandos útiles para decugging.

Ver que Ratchet corre y escucha en localhost:8443 (en el servidor)

ss -ltnp | grep 8443

Ver logs del servicio

sudo journalctl -u intercom -f

En cliente: abre DevTools (F12) -> Console para ver logs de audio.js (onicecandidate, ontrack, state)

Revisar presence files

ls -l /var/www/html/intercom/presence

tail -n +1 /var/www/html/intercom/signals/*.json

=========================================

Puntos importantes / Consideraciones

Proxy Apache: la recomendación es usar Apache HTTPS como proxy WSS -> ws://127.0.0.1:8443/ (esto te evite abrir puertos públicos y usar certificados en Ratchet).
Token HMAC: index.php genera token con la contraseña GENERAL.contraseña en config.txt. Esto ayuda a evitar conexiones WS directas no autorizadas.
ICE: por defecto el cliente usa iceServers vacío (LAN). Si en algún momento tendrás clientes fuera de la LAN, añade STUN/TURN en window.INTERCOM_CONFIG.iceServers y en la configuración correspondiente.
Firewall: si no proxyeas y ejecutas Ratchet en un puerto accesible, abre el puerto en el firewall. Con proxy en Apache no es necesario abrir 8443.
Archivos de imágenes: copia logo.svg, icono.png, altavoz.png y conf.png desde tu proyecto original o deja placeholders.

