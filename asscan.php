<?php

require_once('CDirInfo.php');
require_once('CFileInfo.php');

$GLOBALS['version'] = '1.0.1';
$GLOBALS['author'] = 'Antonio Solo  as@solotony.com';

try
{
    init_mode();

    if (!isset($GLOBALS['settings']['scanpath']) || !$GLOBALS['settings']['scanpath'])
    {
        throw new Exception('Не задан путь');
    }

    if ($GLOBALS['cmd'] == 'scancomp') {
        $GLOBALS['files'] = scan_dir($GLOBALS['settings']['scanpath'], true);
        export_text_file();
        do_compare_current();
    }
    elseif ($GLOBALS['cmd'] == 'scan') {
        $GLOBALS['files'] = scan_dir($GLOBALS['settings']['scanpath'], true);
        export_text_file();
    }
    elseif ($GLOBALS['cmd'] == 'comp') {
        do_compare_last();
    }
    elseif ($GLOBALS['cmd'] == 'clean') {
        do_clean();
    }
    elseif ($GLOBALS['cmd'] == 'compba') {
        do_compare_ba();
    }
    elseif ($GLOBALS['cmd'] == 'genpwd') {
        echo "Пароль: " . $_GET['password'] . $GLOBALS['br'];
        echo "Хеш: " . password_hash($_GET['password'], PASSWORD_BCRYPT) . $GLOBALS['br'];
    }
    elseif ($GLOBALS['cmd'] == 'show') {
        if ($GLOBALS['runhttp']) echo '<pre>';
        var_dump($GLOBALS['settings']);
        if ($GLOBALS['runhttp']) echo '</pre>';
    }
    else {
        throw new Exception('Команда не распознана'.$GLOBALS['br'].
            'Запуск из командной строки: '.$GLOBALS['br'].
            '  asscan -c команда'.$GLOBALS['br'].
            'Запуск из браузера: '.$GLOBALS['br'].
            '  http://path/asscan?cmd=команда&password=пароль'.$GLOBALS['br'].
            'Допустимые команды:'.$GLOBALS['br'].
            '  scancomp - сканирует и сравнивает с предыдущим'.$GLOBALS['br'].
            '  scan - только сканирует'.$GLOBALS['br'].
            '  comp - только сравнивает с предыдущим'.$GLOBALS['br'].
            '  compba - сравнивает с первым'.$GLOBALS['br'].
            '  show - показывает конфиг'.$GLOBALS['br'].
            '  clean - очищает результаты сканирований'.$GLOBALS['br'].
            '  genpwd - генерирует пароль (используется параметр password)'.$GLOBALS['br'].
            '  help - этот экран'.$GLOBALS['br'].
            'Результаты сканирования:'.$GLOBALS['br'].
            '  F+ добавлен файл'.$GLOBALS['br'].
            '  F- удален файл'.$GLOBALS['br'].
            '  F! изменен файл'.$GLOBALS['br'].
            '  D+ добавлена директория'.$GLOBALS['br'].
            '  D- удалена директория'.$GLOBALS['br'].
            '  D! изменена директория'.$GLOBALS['br'].
            '  F% была директория стал файл'.$GLOBALS['br'].
            '  D% был файл стала директория'.$GLOBALS['br']
        );
    }
}
catch (Exception $e)
{
    echo 'Аварийное завершение: ',  $e->getMessage(), "\n";
}

function scan_dir($dirname, $isroot = false)
{
    $dirnamep = $dirname . $GLOBALS['settings']['slash'];

    $dirinfo = new CDirInfo;
    $dirinfo->filepath = $dirname;
    if (is_dir($dirname))
    {
        if ($dh = opendir($dirname))
        {
            while (($file = readdir($dh)) !== false)
            {
                if (is_file($dirnamep.$file))
                {
                    $fi = new CFileInfo;
                    $fi->filename = $file;
                    $fi->filepath = $dirnamep.$file;
                    $fi->filesize = filesize($fi->filepath);
                    $fi->filetime = filectime($fi->filepath);
                    $dirinfo->records[$file] = $fi;
                }
                if (is_dir($dirnamep.$file))
                {
                    if (($file == '.')||($file == '..')) continue;
                    $di = scan_dir($dirnamep.$file, false);
                    $di->name = $file;
                    $dirinfo->records[$file] = $di;
                }
            }
            closedir($dh);
        }
        else
        {
            if ($isroot)
            {
                throw new Exception('Не удается открыть корневой путь');
            }
        }
    }
    else
    {
        if ($isroot)
        {
            throw new Exception('Корневой путь не существует');
        }
    }
    return $dirinfo;
}

function do_clean()
{
    if (is_file($GLOBALS['settings']['datafile01'])) {
        unlink($GLOBALS['settings']['datafile01']);
    }
    if (is_file($GLOBALS['settings']['datafile02'])) {
        unlink($GLOBALS['settings']['datafile02']);
    }
    if (is_file($GLOBALS['settings']['datafile03'])) {
        unlink($GLOBALS['settings']['datafile03']);
    }
    if (is_file($GLOBALS['settings']['datafileBA'])) {
        unlink($GLOBALS['settings']['datafileBA']);
    }
}

function export_text_file()
{
    if (is_file($GLOBALS['settings']['datafile01'])) {
        unlink($GLOBALS['settings']['datafile01']);
    }
    if (is_file($GLOBALS['settings']['datafile02'])) {
        rename($GLOBALS['settings']['datafile02'], $GLOBALS['settings']['datafile01']);
    }
    if (is_file($GLOBALS['settings']['datafile03'])) {
        rename($GLOBALS['settings']['datafile03'], $GLOBALS['settings']['datafile02']);
    }

    if ($fh = fopen($GLOBALS['settings']['datafile03'], 'w+')) {
        $serial = serialize($GLOBALS['files']);
        fprintf($fh, $serial);
        fclose($fh);
    }

    if (!is_file($GLOBALS['settings']['datafileBA'])) {
        copy($GLOBALS['settings']['datafile03'], $GLOBALS['settings']['datafileBA']);
    }
}

function load_json($filename)
{
    if (!is_file($filename)) {
        return null;
    }

    $serial = file_get_contents($filename);

    if (!$serial) {
        return null;
    }
    return unserialize($serial);
}

function do_compare_current()
{
    if (!is_file($GLOBALS['settings']['datafile02'])) {
        return null;
    }
    $olddata = load_json($GLOBALS['settings']['datafile02']);
    if (!$olddata) {
        return null;
    }
    compare_dirs($GLOBALS['files'], $olddata);
}

function do_compare_last()
{
    if (!is_file($GLOBALS['settings']['datafile02'])) {
        return null;
    }
    $olddata = load_json($GLOBALS['settings']['datafile02']);
    if (!$olddata) {
        return null;
    }
    if (!is_file($GLOBALS['settings']['datafile03'])) {
        return null;
    }
    $newdata = load_json($GLOBALS['settings']['datafile03']);
    if (!$newdata) {
        return null;
    }
    compare_dirs($newdata, $olddata);
}

function do_compare_ba()
{
    if (!is_file($GLOBALS['settings']['datafileBA'])) {
        return null;
    }
    $olddata = load_json($GLOBALS['settings']['datafileBA']);
    if (!$olddata) {
        return null;
    }
    if (!is_file($GLOBALS['settings']['datafile03'])) {
        return null;
    }
    $newdata = load_json($GLOBALS['settings']['datafile03']);
    if (!$newdata) {
        return null;
    }
    compare_dirs($newdata, $olddata);
}

function compare_dirs($newdir, $olddir)
{
    if (ignored_path($newdir->filepath))
    {
        echo 'D* ' . $newdir->filepath . $GLOBALS['br'];
        return false;
    }

    $changed = false;
    foreach ($newdir->records as $name => $fi)
    {
        if ($oi = $olddir->records[$name])
        {
            unset($olddir->records[$name]);
            if ($fi->getType())
            {
                //dir
                if ($oi->getType())
                {
                    //dir-dir
                    $dc = compare_dirs($fi, $oi);
                    if ($dc)
                    {
                        $changed = true;
                        //echo 'D! ' . $fi->filepath . $GLOBALS['br'];
                    }
                    else
                    {
                        //echo 'D= ' . $fi->filepath . $GLOBALS['br'];
                    }
                }
                else
                {
                    //dir-file
                    $changed = true;
                    echo 'D% ' . $fi->filepath . $GLOBALS['br'];
                }
            }
            else
            {
                // file
                if ($oi->getType())
                {
                    //file-dir
                    $changed = true;
                    echo 'F% ' . $fi->filepath . $GLOBALS['br'];
                }
                else
                {
                    if ($fi->filesize != $oi->filesize)
                    {
                        $changed = true;
                        echo 'F! ' . $fi->filepath . $GLOBALS['br'];
                    }
                    else
                    {
                        //echo 'F= ' . $fi->filepath . $GLOBALS['br'];
                    }
                }
            }
        }
        else
        {
            $changed = true;
            if ($fi->getType()) {
                echo 'D+ ' . $fi->filepath . $GLOBALS['br'];
                process_add_dir($fi);
            }
            else{
                echo 'F+ ' . $fi->filepath . $GLOBALS['br'];
            }
        }

    }

    foreach ($olddir->records as $name => $oi) {
        if ($oi->getType()) {
            echo 'D- ' . $oi->filepath . $GLOBALS['br'];
            process_del_dir($oi);
        }
        else{
            echo 'F- ' . $oi->filepath . $GLOBALS['br'];
        }
    }

    return $changed;
}

function process_add_dir($newdir)
{
    foreach ($newdir->records as $name => $di) {
        if ($di->getType()) {
            echo 'D+ ' . $di->filepath . $GLOBALS['br'];
            process_add_dir($di);
        }
        else{
            echo 'F+ ' . $di->filepath . $GLOBALS['br'];
        }
    }
}

function process_del_dir($olddir)
{
    foreach ($olddir->records as $name => $di) {
        if ($di->getType()) {
            echo 'D- ' . $di->filepath . $GLOBALS['br'];
            process_del_dir($di);
        }
        else{
            echo 'F- ' . $di->filepath . $GLOBALS['br'];
        }
    }
}

function init_mode()
{
    $GLOBALS['settings'] = parse_ini_file('asscan.ini');

    $sapi = php_sapi_name();

    if ($sapi=='cli') {
        $GLOBALS['runhttp'] = false;
        $GLOBALS['modeinfo'] = 'Запуск из командной строки';
    }
    elseif (substr($sapi,0,3)=='cgi')
    {
        $GLOBALS['runhttp'] = true;
        $GLOBALS['modeinfo'] =  'Запуск в режиме CGI';
    }
    elseif (substr($sapi,0,6)=='apache')
    {
        $GLOBALS['runhttp'] = true;
        $GLOBALS['modeinfo'] =  'Запуск в режиме модуля Apache';
    }
    else
    {
        $GLOBALS['runhttp'] = true;
        $GLOBALS['modeinfo'] =  'Запуск в режиме модуля сервера '.$sapi;
    }

    if ($GLOBALS['runhttp'])
    {
        header("Content-Type: text/html");
        $GLOBALS['br'] = "<br>";
        if (!isset($_GET['cmd'])||!$_GET['cmd'])
        {
            $GLOBALS['cmd'] = 'help';
        }
        else
        {
            $GLOBALS['cmd'] = $_GET['cmd'];
        }
    }
    else
    {
        header("Content-Type: text/plain");
        $GLOBALS['br'] = "\n";
        if (!$options = getopt ( "c:"))
        {
            $GLOBALS['cmd'] = 'help';
        }
        else
        {
            $GLOBALS['cmd'] = $options['c'];
        }

    }

    echo $GLOBALS['modeinfo'] . $GLOBALS['br'];
    echo 'Программа AS Scan v' . $GLOBALS['version'] . $GLOBALS['br'];
    echo $GLOBALS['author'] . $GLOBALS['br'];
    echo 'Текущий каталог:' . getcwd() . $GLOBALS['br'];
    echo 'Команда:' . $GLOBALS['cmd'] . $GLOBALS['br'];

    if ($GLOBALS['cmd'] == 'genpwd') {
        return true;
    }

    if ($GLOBALS['runhttp'])
    {
        if (!password_verify($_GET['password'], $GLOBALS['settings']['password']))
        {
            sleep(3);
            throw new Exception('Пароль "' . $_GET['password'] . '" не совпадает');
        }
    }

    if ($GLOBALS['settings']['slash'] == '\\')
    {
        $GLOBALS['settings']['scanpath'] = str_replace('/', '\\', $GLOBALS['settings']['scanpath']);
    }
    else
    {
        $GLOBALS['settings']['scanpath'] = str_replace('\\', '/', $GLOBALS['settings']['scanpath']);
    }

    $GLOBALS['settings']['scanpath'] = preg_replace('/\\'.$GLOBALS['settings']['slash'].'$/', '', $GLOBALS['settings']['scanpath']);

    foreach ($GLOBALS['settings']['ignore'] as $key => $val)
    {
        $val = preg_replace('/\\'.$GLOBALS['settings']['slash'].'$/', '', $val);
        $GLOBALS['settings']['ignore'][$key] = $GLOBALS['settings']['scanpath'] . $GLOBALS['settings']['slash'] . $val;
    }

    return true;
}

function ignored_path($dirname)
{
    foreach ($GLOBALS['settings']['ignore'] as $key => $val)
    {
        if ($dirname == $val) return true;
    }
    return false;
}
