Nginx fileserver with fancyindex style

# Quick Start

Run the image

```
docker run --name fileserver -d \
   --volume srv:/srv \
   --publish 8080:80 \
   ghcr.io/zveronline/fileserver:latest
```