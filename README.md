ownCloud application to integrate Collabora Online
==================================================
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=owncloud_richdocuments&metric=alert_status)](https://sonarcloud.io/dashboard?id=owncloud_richdocuments)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=owncloud_richdocuments&metric=security_rating)](https://sonarcloud.io/dashboard?id=owncloud_richdocuments)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=owncloud_richdocuments&metric=coverage)](https://sonarcloud.io/dashboard?id=owncloud_richdocuments)

Collabora Online for ownCloud provides collaborating editing functions for text documents, spreadsheets and presentations inside ownCloud for improved productivity.

See also: https://owncloud.com/collabora/collaborative-editing/

### Developing

The easiest way to integrate Collabora with development instance of ownCloud is by disabling SSL for Collabora.

- Start Collabora Server with default settings

    ```
    $ docker run -t -d -p 9980:9980 -e "extra_params=--o:ssl.enable=false" -e "username=admin" -e "password=admin" --name collabora --cap-add MKNOD collabora/code:4.2.5.3
    ```

- Access Collabora Admin at `http://[your-host-public-ip]:9980/loleaflet/dist/admin/admin.html` e.g. `172.16.12.95`,

- Set in `Settings -> Admin -> Additional -> Collabora Online server -> http://[your-host-public-ip]:9980`

### Installation

NOTE: Collabora server needs to be reachable from ownCloud server, and Collabora server needs to be able to reach ownCloud server

NOTE: it is possible to use Collabora Online’s integration with re-compiled and/or re-branded backends.
