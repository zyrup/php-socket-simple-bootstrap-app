# PHP socket simple bootstrap App
Easy way of using web-sockets with PHP on the server side and JS on the client.

## Setup
In order to be able to run the socket-environment it should be sufficient to adjust the port: `CB_SERVER_BIND_HOST` and `CB_SERVER_BIND_PORT` in `server.php` and in `main.js`

## Starting the server
1) Run `server.php`
2) Now you might want to utilize `index.php` via browser

## Browser Compatibility
The service was partially* tested for its basic functionality via browserstack.com on the following devices and browsers:

 - works | **Chrome** browsers above and including version 16*
 - works | **Safari** browsers above and including version 8*
 - works partially | **Safari** browsers above and including version 6*
 - works | **Firefox** browsers above and including version 16*
 - works | **Internet Explorer** browsers above and including version 10*
- works | **Edge** browsers above and including version 16*
- works partially | **Edge** browsers above and including version 15*
- works | **Opera** browsers above and including version 15*
- works | **Android** browsers above and including version 4.4*

*presumably all possible versions
