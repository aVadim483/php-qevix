<?php
use \avadim\Qevix\Qevix;

include '../src/Qevix.php';

$sDefaultText = <<<EOD
Перед знаками препинания пробелов быть не должно ,а вот после - обязательно.
Правильная расстановка пробелов вокруг скобок : ( это текст в скобках )и без оных.
Кавычки меняются на "ёлочки", а "вложенные кавычки - на "лапки""
Дефисы в словах, вроде "дочки-матери" сохраняются, а между словами - заменяются на тире
Также заменяются     :
знак копирайта - (с)
символ зарегистрированного товарного знака - (R)
символ плюс-минус - +/-
три точки подряд на многоточие - ...

<a href="#">А это текст ссылки</a>
EOD;

if (isset($_POST['demo_text'])) {
    $sDemoText = $_POST['demo_text'];
} else {
    $sDemoText = $sDefaultText;
}
if (isset($_POST['mode'])) {
    $sDemoMode = $_POST['mode'];
} else {
    $sDemoMode = 'text';
}

$aConfig = include __DIR__ . '/config.html.php';
$oQevix = new Qevix($aConfig);
switch ($sDemoMode) {
    case 'html':
        $sResultText = $oQevix->parse($sDemoText);
        break;
    case 'plain':
        $sResultText = nl2br($oQevix->plain($sDemoText));
        break;
    default:
        $sResultText = $oQevix->text($sDemoText);
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>avadim/qevix</title>
    <style>
        body {
            font-family: serif Arial;
            font-size: 16px;
        }
        table {
            width: 100%;

        }
        td {
            vertical-align: top;
            width: 50%;
            padding: 10px;
        }
        textarea {
            width: 100%;
            min-height: 300px;
        }
        textarea, div {
            font-family: Geneva, Arial, Helvetica, sans-serif;
            font-size: 1rem;
            padding: 10px;
        }
    </style>
</head>
<body>
<h1>avadim/qevix</h1>
<table>
    <tr>
        <td>
            <form action="" method="post">
                <textarea name="demo_text"><?=htmlspecialchars($sDemoText);?></textarea>
                <button type="submit" name="mode" value="html">Обработать</button>
                <button type="submit" name="mode" value="text">Режим 'text'</button>
                <button type="submit" name="mode" value="plain">Режим 'plain'</button>
            </form>
        </td>
        <td>
            <div>
                <?=$sResultText;?>
            </div>
        </td>
    </tr>
</table>
</body>
</html>