ownCloud application to integrate Collabora Online
==================================================

Collabora Online for ownCloud provides collaborating editing functions for text documents, spreadsheets and presentations inside ownCloud for improved productivity.

Trigger CI

See also: https://owncloud.com/collabora/collaborative-editing/

### Developing

The easiest way to integrate Collabora with development instance of ownCloud is by disabling SSL for Collabora.

- Start Collabora Server with default settings

    ```
    $ docker run -t -d \
    -p 9980:9980 \
    -e "username=admin" \
    -e "password=admin" \
    --name collabora \
    --cap-add MKNOD \
    collabora/code
    ```

- Update Collabora Server docker and modify SSL settings

    ```
    $ docker exec -it collabora /bin/bash -c "apt-get -y update && apt-get -y install xmlstarlet && xmlstarlet ed --inplace -u \"/config/ssl/enable\" -v false /etc/loolwsd/loolwsd.xml && xmlstarlet ed --inplace -u \"/config/ssl/termination\" -v false /etc/loolwsd/loolwsd.xml"
    ```

- Restart updated docker, wait 30 seconds and retrieve IP of the server

    ```
    $ docker restart collabora
    ```

- Access Collabora Admin at `http://[your-host-public-ip]:9980/loleaflet/dist/admin/admin.html` e.g. `172.16.12.95`, and set in `Settings -> Admin -> Additional -> Collabora Online server -> http://[your-host-public-ip]:9980`

Note: it is possible to use Collabora Online’s integration with re-compiled and/or re-branded backends.
