Php, restfull json(p) service via Slim framework

Calling api
-----------

You can manually call api from console via curl like this:

    curl -i -X GET http://localhost/slim/api/users
    curl -i -X GET http://localhost/slim/api/users/1
    curl -i -X POST -H "Content-Type: application/json" -d "{\"name\": \"test\", \"email\": \"test@example.com\", \"age\": 25}" http://localhost/slim/api/users
    curl -i -X PUT -H "Content-Type: application/json" -d "{\"id\": 3, \"name\": \"test\", \"email\": \"test@example.com\", \"age\": 23}" http://localhost/slim/api/users/3
    curl -i -X DELETE http://localhost/slim/api/users/3

Also you can call api from browser console like this:

    var users = $.restInterfaceTo('/slim/api/users');
    users.all(console.log);
    users.one(1, console.log);
    users.add({name: 'Alex', mail: 'alex@example.com', age: 27}, console.log);
    users.put(3, {name: 'alex', mail: 'alex@gmail.com', age: 23}, console.log);
    users.del(3, console.log);

Jsonp
-----

Just add `&callback=cb` to any resuest and you will see what happens

Installing Database
-------------------

    mysql -u root --password=[PASSWORD] < db.sql
