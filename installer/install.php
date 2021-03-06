<?php
/**
 * Q.uote.me installer
 * @package quoteme
 * @author Daniel Douat <daniel.douat@aelys-info.fr>
 */

/**
 * Loading libs
 */

require '../config.php';
require '../libs/mysql.php';
require '../libs/timply.php';

function dbConnexion($host, $name, $user, $pass)
{
    try {
        $instance = new PDO('mysql:host=' . $host . ';dbname=' . $name, $user, $pass);
        $instance->query("SET NAMES 'utf8'");
        $result = $instance;
    } catch (Exception $e) {
        $error = $e->getCode();
        $result = $error;
    }
    return $result;
}

function checkDbIds($host, $user, $password, $dbName, $tableName)
{
    $connexion = dbConnexion($host, $dbName, $user, $password);
    $state     = array('dbHost' => TRUE, 'dbIds' => TRUE, 'dbName' => TRUE, 'tableName' => TRUE);
    if (!is_object($connexion)) {
        if ($connexion === 2002) {
            $state['dbHost'] = 10;
        }
        if ($connexion === 1045) {
            $state['dbIds'] = 20;
        }
        if ($connexion === 1044) {
            $state['dbName'] = 30;
        }
    }
    if (empty($dbName)) {
        $state['dbName'] = 31;
    }
    if (empty($tableName)) {
        $state['tableName'] = 40;
    }
    else {
        if (ifDbTableExist($connexion, $tableName) === FALSE) {
            $state['tableName'] = 41;
        }
        else {
            $state = TRUE;
        }
    }
    return $state;
}

function ifDbTableExist($instance, $table)
{
    if (is_object($instance)) {;
        $tables = $instance->prepare("SHOW TABLES LIKE '" . $table . "'");
        $tables->execute();
        $result = $tables->fetchAll(PDO::FETCH_OBJ);
        if (count($result) > 0) {
            $tableExist = FALSE;
        }
        else {
            $tableExist = TRUE;
        }
    }
    return $tableExist;
}

function checkConfigFile()
{
    $fileName = 'config.php';
    $fileUri  = '../';
    return is_writable($fileUri . $fileName);
}

function writeConfigFile($newOptions)
{
    $fileName = 'config.php';
    $fileUri  = '../';
    $configContent = file_get_contents($fileUri . $fileName);
    foreach ($newOptions as $key => $value) {
        list($type, $option) = explode(">", $key);
        //$keys = explode(">", $key);
        if (is_string($value)) {
            $value = "'" . str_replace("'", "\'", $value) . "'";
        }
        $pattern       = '/\$' . $type . '\[\'' . $option . '\'\]\s*=\s*[\'"]{0,1}.*[\'"]{0,1};/i';
        $replace       = '$' . $type . '[\'' . $option . '\'] = ' .$value . ';';
        $configContent = preg_replace($pattern, $replace, $configContent);
    }
    file_put_contents($fileUri . $fileName, $configContent, LOCK_EX);
}

function httpAcceptLanguageToArray()
{
    $string   = str_replace(',', ';', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $elements = explode(';', $string);
    $nb       = count($elements);
    $q        = "1";
    for ($i = 0; $i < $nb; $i++) {
        if (strpos($elements[$i], 'q=') === FALSE) {
            $n = $i + 1;
            if (strpos($elements[$n], 'q=') !== FALSE) {
                $q = substr($elements[$n], 2);
            }
            $languages[strtolower($elements[$i])] = $q;
        }
        unset($q);
    }
    arsort($languages);
    return $languages;
}

function getUserLanguage()
{
    $languages = array_flip(httpAcceptLanguageToArray());
    $languages = array_values($languages);
    $favorite  = $languages[0];
    if (strlen($favorite) === 2) {
        $favorite .= '-' . $favorite;
    }
    $favorite = preg_replace_callback('/(\w+)-(\w+)/i',
        function($matches) {
            return $matches[1] . "_" . strtoupper($matches[2]);
        }, $favorite);
    return $favorite;
}

function arrayToSelect($languages, $post)
{
    if (is_array($languages)) {
        $select = '<select name="lang" id="lang" onChange="javascript:this.form.submit();">';
        foreach ($languages as $option) {
            if ($post === $option || (getUserLanguage() === $option && $selected !== 'selected')) {
                $selected = 'selected';
            }
            else {
                unset($selected);
            }
            $select .= '<option value="' . $option . '" ' . $selected . '>' . $option . '</option>';
        }
        $select .= '</select>';
    }
    return $select;
}

function listAvailableLanguages()
{
    if (($dir = opendir('../lang'))) {
        while (($file = readdir($dir)) !== FALSE) {
            if ($file !== '.' && $file !== '..') {
                $languages[] = substr($file, 0, -4);
            }
            unset($selected);
        }
        closedir($dir);
    }
    return $languages;
}

function install($resetPassword = FALSE)
{
    $password = hash('sha256', $_POST['password']);
    if ($resetPassword !== TRUE) {
        $table = 'CREATE TABLE IF NOT EXISTS `' . $_SESSION['dbTable'] . '` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote` text NOT NULL,
  `author` varchar(100) NOT NULL,
  `source` varchar(100) NOT NULL,
  `tags` text NOT NULL,
  `permalink` char(6) NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;';

        $instance = dbConnexion($_SESSION['dbHost'], $_SESSION['dbName'], $_SESSION['dbUser'], $_SESSION['dbPass']);
        $stmt     = $instance->prepare($table);
        $stmt->execute();
        if ($stmt !== FALSE) {
            writeConfigFile(array('config>dbHost' => $_SESSION['dbHost'], 'config>dbName' => $_SESSION['dbName'],
                'config>dbUser' => $_SESSION['dbUser'], 'config>dbPass' => $_SESSION['dbPass'],
                'config>dbTable' => $_SESSION['dbTable'], 'config>lang' => $_SESSION['lang'],
                'config>user' => $_SESSION['user'], 'config>password' => $password, 'config>email' => $_SESSION['email']));
            $install = TRUE;
        }
    }
    else {
        writeConfigFile(array('config>user' => $_SESSION['user'], 'config>password' => $password, 'config>email' => $_SESSION['email']));
        $install = TRUE;
    }
    return $install;
}

if (empty($config['password'])) {
    session_start();
    $lang = $_SESSION['lang'];
    if (empty($_POST['lang']) && empty($lang)) {
        $lang = getUserLanguage();
    }
    elseif (!empty($_POST['lang'])) {
        $lang = $_POST['lang'];
    }

    timply::setUri('');
    timply::setFileName('installer.html');
    timply::addDictionary('../lang/' .$lang . '.php');
    $html = new timply();
    if (!empty($_POST) && implode('', $_POST) !== $lang) {
        if (empty($GLOBALS['config']['dbName'])) {
            if (empty($_POST['dbhost']))    $_POST['dbhost'] = 'localhost';
            if (empty($_POST['user']))      $_POST['user']   = $_POST['dbuser'];
            if (empty($_POST['password']))  $_POST['password']   = $_POST['dbpass'];
            $_SESSION['lang']    = $_POST['lang'];
            $_SESSION['dbHost']  = $_POST['dbhost'];
            $_SESSION['dbUser']  = $_POST['dbuser'];
            $_SESSION['dbPass']  = $_POST['dbpass'];
            $_SESSION['dbName']  = $_POST['dbname'];
            $_SESSION['dbTable'] = $_POST['dbtable'];
            $_SESSION['user']    = $_POST['user'];
            $_SESSION['pass']    = $_POST['password'];
            $_SESSION['email']   = $_POST['email'];
            $dbState             = checkDbIds($_SESSION['dbHost'], $_SESSION['dbUser'], $_SESSION['dbPass'], $_SESSION['dbName'], $_SESSION['dbTable']);
        }
        else {
            $_SESSION['lang']    = $GLOBALS['config']['lang'];
            $_SESSION['dbHost']  = $GLOBALS['config']['dbHost'];
            $_SESSION['dbUser']  = $GLOBALS['config']['dbUser'];
            $_SESSION['dbPass']  = $GLOBALS['config']['dbPass'];
            $_SESSION['dbName']  = $GLOBALS['config']['dbName'];
            $_SESSION['dbTable'] = $GLOBALS['config']['dbTable'];
            $_SESSION['user']    = $GLOBALS['config']['user'];
            $_SESSION['email']   = $GLOBALS['config']['email'];
            $_SESSION['pass']    = $_POST['password'];
            $dbState             = TRUE;
            $resetPassword       = TRUE;
        }
        $html->setElement('postDbHost', $_SESSION['dbHost']);
        $html->setElement('postDbUser', $_SESSION['dbUser']);
        $html->setElement('postDbPass', $_SESSION['dbPass']);
        $html->setElement('postDbName', $_SESSION['dbName']);
        $html->setElement('postDbTable', $_SESSION['dbTable']);
        $html->setElement('postUser', $_SESSION['user']);
        $html->setElement('postPass', $_SESSION['pass']);
        $html->setElement('postEmail', $_SESSION['email']);

        $confState = checkConfigFile();

        if ($dbState !== TRUE) {
            if (is_array($dbState)) {
                foreach ($dbState as $key => $value) {
                    if (is_int($value)) {
                        $html->setElement('notChecked' .  $key, 'visible');
                        $html->setElement('dbError', '[trad::db_error_' . $value . ']', 'dbErrors');
                    }
                    elseif ($value === TRUE) {
                        $html->setElement('checked' .  $key, 'visible');
                    }
                }
            }
        }
        else {
            $html->setElement('checkedDb', 'visible');
            $html->setElement('dbSuccess', '[trad::db_infos_correct]');
        }
        if ($confState === FALSE) {
            $html->setElement('notCheckedConfigFile', 'visible');
            $html->setElement('configFileError', '[trad::config_file_is_not_writable]');
        }
        else {
            $html->setElement('checkedConfigFile', 'visible');
            $html->setElement('configFileSuccess', '[trad::config_file_is_writable]');
        }
        if ($dbState === TRUE && $confState === TRUE) {
            if ($resetPassword) {
                $value = '[trad::update_datas]';
            }
            else {
                $value = '[trad::install_script]';
            }
            $GLOBALS['html']->setElement('checked', 'checked');
            $html->setElement('install', '<input type="submit" name="install" value="' . $value . '">');
            $canInstall = TRUE;
        }
        if (isset($_POST['install']) && $canInstall) {
            $install = install($resetPassword);
        }
    }
    $html->setElement('test', '<input type="submit" name="test" value="[trad::test_datas]">');
    $html->setElement('langSelect', arrayToSelect(listAvailableLanguages(), $lang));
    echo $html->returnHtml();
}
?>