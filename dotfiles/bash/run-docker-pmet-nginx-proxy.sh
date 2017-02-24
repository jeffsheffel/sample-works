
# Create/Run Nginx Proxy Container
# This isn't required, but might make your life easier. Instead of remembering which port each app is on, you can use Nginx Proxy.
# See the VIRTUAL_HOST environment variable in each other container.

docker run -d \
-p 80:80 \
-p 443:443 \
--name nginx-proxy \
--restart="unless-stopped" \
--privileged=true \
-e CERT_NAME="" \
-v /var/run/docker.sock:/tmp/docker.sock:ro \
jwilder/nginx-proxy

