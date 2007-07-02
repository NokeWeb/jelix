<?php
/**
* @package    jelix
* @subpackage auth
* @author     Laurent Jouanneau
* @contributor
* @copyright  2001-2005 CopixTeam, 2005-2007 Laurent Jouanneau
* Classe orginellement issue d'une branche experimentale du
* framework Copix 2.3dev. http://www.copix.org (jAuth)
* Une partie du code est sous Copyright 2001-2005 CopixTeam (licence LGPL)
* Auteur initial : Laurent Jouanneau
* Adaptée pour Jelix par Laurent Jouanneau
* @licence  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*
*/
#ifnot ENABLE_PHP_JELIX
/**
 * interface for auth drivers
 * @package    jelix
 * @subpackage auth
 * @static
 */
interface jIAuthDriver {
    /**
     * constructor
     * @param array $params driver parameters, written in the ini file of the auth plugin
     */
    function __construct($params);

    /**
     * creates a new user object, with some first datas..
     * Careful : it doesn't create a user in a database for example. Just an object.
     * @param string $login the user login
     * @param string $password the user password
     * @return object the returned object depends on the driver
     */
    public function createUserObject($login, $password);

    /**
    * store a new user.
    *
    * It create the user in a database for example
    * should be call after a call of createUser and after setting some of its properties...
    * @param object $user the user data container
    */
    public function saveNewUser($user);

    /**
     * Erase user datas of the user $login
     * @param string $login the login of the user to remove
     */
    public function removeUser($login);

    /**
    * save updated datas of a user
    * warning : should not save the password !
    * @param object $user the user data container
    */
    public function updateUser($user);

    /**
     * return user data corresponding to the given login
     * @param string $login the login of the user
     * @return object the user data container
     */
    public function getUser($login);

    /**
     * construct the user list
     * @param string $pattern '' for all users
     * @return array array of user object
     */
    public function getUserList($pattern);

    /**
     * change a user password
     *
     * @param string $login the login of the user
     * @param string $newpassword
     */
    public function changePassword($login, $newpassword);

    /**
     * verify that the password correspond to the login
     * @param string $login the login of the user
     * @param string $password the password to test
     * @return object|false
     */
    public function verifyPassword($login, $password);
}

#endif

/**
 * This is the main class for authentification process
 * @package    jelix
 * @subpackage auth
 */
class jAuth {

    /**
     * Load the configuration of authentification, stored in the auth plugin config
     * @return array
     */
    protected static function  _getConfig(){
        static $config = null;
        if($config == null){
            global $gJCoord;
            $plugin = $gJCoord->getPlugin('auth');
            if($plugin === null){
                throw new jException('jelix~auth.error.plugin.missing');
            }
            $config = & $plugin->config;
        }
        return $config;
    }

    /**
     * load the auth driver
     * @return jIAuthDriver
     */
    protected static function _getDriver(){
        static $driver = null;
        if($driver == null){
            $config = self::_getConfig();
            global $gJConfig;
            $db = strtolower($config['driver']);
            if(!isset($gJConfig->_pluginsPathList_auth) 
                || !isset($gJConfig->_pluginsPathList_auth[$db])
                || !file_exists($gJConfig->_pluginsPathList_auth[$db]) ){
                 throw new jException('jelix~auth.error.driver.notfound',$db);
            }
            require_once($gJConfig->_pluginsPathList_auth[$db].$db.'.auth.php');
            $dname = $config['driver'].'AuthDriver';
            $driver = new $dname($config[$config['driver']]);
        }
        return $driver;
    }

    /**
     * load user datas
     *
     * This method returns an object, generated by the driver, and which contains
     * datas corresponding to the given login. This method should be called if you want
     * to update datas of a user. see updateUser method.
     *
     * @param string $login
     * @return object the user
     */
    public static function getUser($login){
        $dr = self::_getDriver();
        return $dr->getUser($login);
    }

    /**
     * Create a new user object
     * 
     * You should call this method if you want to create a new user. It returns an object,
     * representing a user. Then you should fill its properties and give it to the saveNewUser
     * method.
     * 
     * @param string $login the user login
     * @param string $password the user password (not encrypted)
     * @return object the returned object depends on the driver
     * @since 1.0b2
     */
    public static function createUserObject($login,$password){
        $dr = self::_getDriver();
        return $dr->createUserObject($login,$password);
    }

    /**
     * Save a new user 
     * 
     * if the saving has succeed, a AuthNewUser event is sent
     * The given object should have been created by calling createUserObject method :
     *
     * example :
     *  <pre>
     *   $user = jAuth::createUserObject('login','password');
     *   $user->email ='bla@foo.com';
     *   jAuth::saveNewUser($user);
     *  </pre>
     *  the type of $user depends of the driver, so it can have other properties.
     *
     * @param  object $user the user data
     * @return object the user (eventually, with additional datas)
     */
    public static function saveNewUser($user){
        $dr = self::_getDriver();
        if($dr->saveNewUser($user)){
            jEvent::notify ('AuthNewUser', array('user'=>$user));
        }
        return $user;
    }

    /**
     * update user datas
     * 
     * It send a AuthUpdateUser event if the saving has succeed. If you want
     * to change the user password, you must use jAuth::changePassword method
     * instead of jAuth::updateUser method.
     *
     * The given object should have been created by calling getUser method.
     * Example :
     *  <pre>
     *   $user = jAuth::getUser('login');
     *   $user->email ='bla@foo.com';
     *   jAuth::updateUser($user);
     *  </pre>
     *  the type of $user depends of the driver, so it can have other properties.
     * 
     * @param object $user  user datas
     */
    public static function updateUser($user){
        $dr = self::_getDriver();
        if($user = $dr->updateUser($user)){
            jEvent::notify ('AuthUpdateUser', array('user'=>$user));
        }
    }

    /**
     * remove a user
     * send first AuthCanRemoveUser event, then if ok, send AuthRemoveUser
     * and then remove the user.
     * @param string $login the user login
     * @return boolean true if ok
     */
    public static function removeUser($login){
        $dr = self::_getDriver();
        $eventresp = jEvent::notify ('AuthCanRemoveUser', array('login'=>$login));
        foreach($eventresp->getResponse() as $rep){
            if(!isset($rep['canremove']) || $rep['canremove'] === false){
                return false;
            }
        }
        jEvent::notify ('AuthRemoveUser', array('login'=>$login));
        return $dr->removeUser($login);
    }

    /**
     * construct the user list
     * @param string $pattern '' for all users
     * @return array array of object
     */
    public static function getUserList($pattern = '%'){
        $dr = self::_getDriver();
        return $dr->getUserlist($pattern);
    }

    /**
     * change a user password
     *
     * @param string $login the login of the user
     * @param string $newpassword the new password (not encrypted)
     * @return boolean true if the change succeed
     */
    public static function changePassword($login, $newpassword){
        $dr = self::_getDriver();
        return $dr->changePassword($login, $newpassword);
    }

    /**
     * verify that the password correspond to the login
     * @param string $login the login of the user
     * @param string $password the password to test (not encrypted)
     * @return object|false  if ok, returns the user as object
     */
    public static function verifyPassword($login, $password){
        $dr = self::_getDriver();
        return $dr->verifyPassword($login, $password);
    }

    /**
     * authentificate a user, and create a user in the php session
     * @param string $login the login of the user
     * @param string $password the password to test (not encrypted)
     * @return boolean true if authentification is ok
     */
    public static function login($login, $password){

        $dr = self::_getDriver();
        if($user = $dr->verifyPassword($login, $password)){

            $eventresp = jEvent::notify ('AuthCanLogin', array('login'=>$login, 'user'=>$user));
            foreach($eventresp->getResponse() as $rep){
                if(!isset($rep['canlogin']) || $rep['canlogin'] === false){
                    return false;
                }
            }

            $_SESSION['JELIX_USER'] = $user;
            jEvent::notify ('AuthLogin', array('login'=>$login));
            return true;
        }else
            return false;
    }

    /**
     * logout a user and delete the user in the php session
     */
    public static function logout(){
        jEvent::notify ('AuthLogout', array('login'=>$_SESSION['JELIX_USER']->login));
        $_SESSION['JELIX_USER'] = new jDummyAuthUser();
        jAcl::clearCache();
    }

    /**
     * Says if the user is connected
     * @return boolean
     */
    public static function isConnected(){
        return (isset($_SESSION['JELIX_USER']) && $_SESSION['JELIX_USER']->login != '');
    }

   /**
    * return the user stored in the php session
    * @return object the user datas
    */
    public static function getUserSession (){
      if (! isset ($_SESSION['JELIX_USER'])){
            $_SESSION['JELIX_USER'] = new jDummyAuthUser();
      }
      return $_SESSION['JELIX_USER'];
    }

    /**
     * deprecated method. see CreateUserObject
     * @param string $login the user login
     * @param string $password the user password
     * @return object the returned object depends on the driver
     * @deprecated
     */
    public static function createUser($login,$password){
        return self::createUserObject($login,$password);
    }

}
?>
