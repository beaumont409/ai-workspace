server {
    listen 80;
    listen [::]:80;

    server_name oemunlocks.com www.oemunlocks.com;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;

    server_name oemunlocks.com www.oemunlocks.com;

    root /var/www/oemunlocks;
    index index.html;

    ssl_certificate /etc/letsencrypt/live/oemunlocks.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/oemunlocks.com/privkey.pem;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location /api/ {
        proxy_pass http://127.0.0.1:8081/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    location /landing/ {
        proxy_pass http://127.0.0.1:5000/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    location / {
        try_files $uri $uri/ =404;
    }


    location ~ /\. { deny all; }
    location ~* \.(?:sqlite(?:3)?|db)(?:[-.].*)?$ { deny all; }
    location ~* (?:\.(?:bak|backup|old|orig|save|sql|zip|tar|gz)(?:[-.].*)?$|~$) { deny all; }
    location ~ ^/payment\.(?:beautiful|current|before-professional)$ { deny all; }
    location ~ ^/landing-backup- { deny all; }
    location = /app.php { deny all; }
    location = /orders.php {
        client_max_body_size 16k;
        limit_req zone=oem_admin burst=5 nodelay;
        limit_req_status 429;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/oemunlocks-fpm/php.sock;
    }
    location ~ \.php$ {
        client_max_body_size 16k;
        limit_req zone=oem_php burst=20 nodelay;
        limit_req_status 429;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/oemunlocks-fpm/php.sock;
    }
}
