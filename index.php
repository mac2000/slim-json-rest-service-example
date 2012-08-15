<?php
require_once 'Slim/Slim/Slim.php';
require_once 'config.php';

// Application specific exceptions
// Will return 404 Not Found response code
class NotFoundException extends Exception {}
// Will return 403 Forbidden response code
class ForbiddenException extends Exception {}
// Will return 400 Bad Request response code
class ValidationException extends Exception {}

// Abstract class providing singleton behaviour
// will be used by handlers and database classes
abstract class Singleton {
    protected static $instances = array();
    protected function __construct(){}
    private function __clone(){}
    private function __wakeup(){}

    public static function getInstance() {
        $class = get_called_class();
        if(!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }
}

// Simple PDO database wrapper
// it is not needed by this example and only
// used for prettier request handlers code examples
class Database extends Singleton {
    protected static $dbh;
    protected function __construct() {
        self::$dbh = new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS);
        self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function all($sql, $params = array()) {
        $stmt = self::$dbh->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    public function one($sql, $params = array()) {
        $stmt = self::$dbh->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }
    public function execute($sql, $params = array()) {
        $stmt = self::$dbh->prepare($sql);
        $stmt->execute($params);
        if(preg_match('/insert/i', $sql)) return self::$dbh->lastInsertId();
        else return $stmt->rowCount();
    }
}

// Main class defining interface for request handlers
// each class must provide basic CRUD method
abstract class RestApiInterface extends Singleton {
    protected $db;
    protected $app;
    protected function __construct() {
        $this->db = Database::getInstance();
        $this->app = Slim::getInstance();
    }
    abstract public function all();
    abstract public function one($id);
    abstract public function add($data);
    abstract public function put($id, $data);
    abstract public function del($id);
    abstract public function validate($data);
}

// Example of class implementing interface
class Users extends RestApiInterface {
    public function all() {
        return $this->db->all('SELECT * FROM users');
    }
    public function one($id) {
        return $this->db->one('SELECT * FROM users WHERE id=:id', array('id' => $id));
    }
    public function add($data) {
        $this->validate($data);

        if($this->db->one('SELECT * FROM users WHERE email=:email', array('email' => $data->email))) throw new ValidationException('Duplicate email');

        $data->id = $this->db->execute('INSERT INTO users VALUES(NULL, :name, :email, :age)', (array)$data);
        return $data;
    }
    public function put($id, $data) {
        $this->validate($data);

        $this->db->execute('UPDATE users SET name=:name, email=:email, age=:age WHERE id=:id', array_merge((array)$data, array('id' => $id)));
        return $data;
    }
    public function del($id) {
        $this->db->execute('DELETE FROM users WHERE id=:id', array('id' => $id));
        return true;
    }
    public function validate($data) {
        if(empty($data->name)) throw new ValidationException('Name is required');
        if(empty($data->email)) throw new ValidationException('Email is required');
        if(empty($data->age)) throw new ValidationException('Age is required');
        if(!filter_var($data->email, FILTER_VALIDATE_EMAIL)) throw new ValidationException('Wrong email');
        if(!filter_var($data->age, FILTER_VALIDATE_INT, array('min_range' => 1, 'max_range' => 999))) throw new ValidationException('Wrong age');
    }
}

// And here is slim app
// we are going to catch all requests that starts with api
// and give them to appropriate classes method declared above
$app = new Slim(array(
    'cookies.secret_key' => SECRET
));
// Auth samples
$app->get('/api/logout', function(){
    Slim::getInstance()->deleteCookie('auth');
});
$app->post('/api/login', function(){
    $app = Slim::getInstance();
    try {
        $data = json_decode($app->request()->getBody());

        if($data->login == DBUSER && $data->password == DBPASS) {
            $app->setEncryptedCookie('auth', $data->login, '7 days');
        }
    }
    catch(Exception $e) {
        $app->halt(400, $e->getTrace());
    }
});
$app->map('/api/:entity(/:id)', function($entity, $id = null){
    $app = Slim::getInstance();
    try {
        $class = ucfirst($entity);

        // Check that class exists
        if(!class_exists($class)) throw new NotFoundException();
        // Check that class implements RestApiInterface
        if(!is_subclass_of($class, 'RestApiInterface')) throw new NotFoundException();

        $class = $class::getInstance();
        $method = $app->request()->getMethod();

        $auth = $app->getEncryptedCookie('auth');
        if(in_array($method, array('POST', 'PUT', 'DELETE')) && !$auth) throw new ForbiddenException();

        if($method == 'GET' && $id == null) $res = $class->all();
        else if($method == 'GET' && $id != null) $res = $class->one($id);
        else if($method == 'POST') $res = $class->add(json_decode($app->request()->getBody()));
        else if($method == 'PUT' && $id != null) $res = $class->put($id, json_decode($app->request()->getBody()));
        else if($method == 'DELETE' && $id != null) $res = $class->del($id);
        else $app->halt(501); // Not implemented

        if(empty($res)) throw new NotFoundException();

        $json = json_encode($res);
        $cb = isset($_GET['callback']) ? $_GET['callback'] : false;
        if($cb) $json = "$cb($json)";
        echo $json;
    }
    catch(ValidationException $e) {
        $app->halt(400, $e->getMessage());
    }
    catch(ForbiddenException $e) {
        $app->halt(403);
    }
    catch(NotFoundException $e) {
        $app->halt(404);
    }
    catch(Exception $e) {
        $app->halt(500, $e->getMessage());
    }
})->via('GET', 'POST', 'PUT', 'DELETE')->conditions(array('id' => '\d+'));
$app->run();
