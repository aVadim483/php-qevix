<?php

namespace avadim\Qevix;

class Qevix
{
    const NIL           = 0x0;
    const PRINTABLE     = 0x1;
    const ALPHA         = 0x2;
    const NUMERIC       = 0x4;
    const PUNCTUATION   = 0x8;
    const SPACE         = 0x10;
    const NL            = 0x20;
    const TAG_NAME      = 0x40;
    const TAG_PARAM_NAME= 0x80;
    const TAG_QUOTE     = 0x100;
    const TEXT_QUOTE    = 0x200;
    const TEXT_BRACKET  = 0x400;
    const SPECIAL_CHAR  = 0x800;
    //const = 0x1000;
    //const = 0x2000;
    //const = 0x4000;
    //const = 0x8000;
    const NO_PRINT      = 0x10000;

    public $tagsRules   = [];
    public $entities    = ['"' => '&#34;', "'" => '&#39;', '<' => '&#60;', '>' => '&#62;', '&' => '&#38;'];
    public $quotes      = [['«', '»'], ['„', '“']];
    public $bracketsALL = ['<' => '>', '[' => ']', '{' => '}', '(' => ')'];
    public $bracketsSPC = ['[' => ']', '{' => '}'];
    public $dash        = '—';
    public $nl          = "\n";
    public $autoReplace = [];
    public $linkProtocolAllowed = ['https', 'http', 'ftp'];
    public $linkProtocolDefault = 'https';

    protected $textBuf  = [];
    protected $textLen  = 0;

    protected $prevChar;
    protected $prevCharOrd = 0;
    protected $prevCharClass = self::NIL;
    protected $prevPos  = -1;

    protected $curChar;
    protected $curCharOrd = 0;
    protected $curCharClass = self::NIL;
    protected $curPos   = -1;

    protected $nextChar;
    protected $nextCharOrd = 0;
    protected $nextCharClass = self::NIL;
    protected $nextPos  = -1;

    protected $curTag;
    protected $statesStack = [];
    protected $quotesOpened = 0;
    protected $specialChars = [];

    protected $isXHTMLMode = false; // режим XHTML - короткие теги вида <tag/> и атрибуты тега обязательно со значение attr="attr"
    protected $isAutoBrMode = true; // перевод строки автоматически преобразуется в тег <br>
    protected $isSaveNL = true; // сам исмвол перевода строки сохраняется, даже если добавляется <br>
    protected $limitNL = 2; // число переводов строки подряд
    protected $isAutoLinkMode = true;
    protected $isSpecialCharMode = false;
    protected $typoMode = true;
    protected $br = '<br>';
    protected $mode = 'html';

    protected $plainText = '';
    protected $plainTextLen = 0;
    protected $plainTextLimit = 0;
    protected $plainTextBreak = 0;

    protected $errorsList = [];


    /**
     * Классификация тегов
     */
    const TAG_ALLOWED           = 1; // Тег допустим
    const TAG_ATTR_ALLOWED      = 2; // Параметр тега допустим
    const TAG_ATTR_REQUIRED     = 3; // Параметр тега является необходимым
    const TAG_SHORT             = 4; // Тег короткий
    const TAG_CUT               = 5; // Тег необходимо вырезать вместе с его контентом
    const TAG_GLOBAL_ONLY       = 6; // Тег может находиться только в "глобальной" области видимости (не быть дочерним к другим)
    const TAG_PARENT_ONLY       = 7; // Тег может содержать только другие теги
    const TAG_CHILD_ONLY        = 8; // Тег может находиться только внутри других тегов
    const TAG_PARENT            = 9; // Тег родитель относительно дочернего тега
    const TAG_CHILD             = 10; // Тег дочерний относительно родительского
    const TAG_PREFORMATTED      = 11; // Преформатированные теги
    const TAG_PARAM_AUTO_ADD    = 12; // Автодобавление параметров со значениями по умолчанию
    const TAG_NO_TYPOGRAPHY     = 13; // Тег с отключенным типографированием
    const TAG_EMPTY             = 14; // Пустой не короткий тег
    const TAG_NO_AUTO_BR        = 15; // Тег в котором не нужна авто-расстановка <br>
    const TAG_BLOCK_TYPE        = 16; // Тег после которого нужно удалять один перевод строки
    const TAG_BUILD_CALLBACK    = 17; // Тег обрабатывается и строится callback-функцией
    const TAG_EVENT_CALLBACK    = 18; // Тег обрабатывается callback-функцией для сбора информации

    const TAG_PARAM_ALLOWED     = 2; // Параметр тега допустим
    const TAG_PARAM_REQUIRED    = 3; // Параметр тега является необходимым

    /**
     * Классы символов из symbolclass.php
     */
    protected $charClasses = [0=>65536,1=>65536,2=>65536,3=>65536,4=>65536,5=>65536,6=>65536,7=>65536,8=>65536,9=>65552,10=>65568,
        11=>65536,12=>65536,13=>65568,14=>65536,15=>65536,16=>65536,17=>65536,18=>65536,19=>65536,20=>65536,21=>65536,22=>65536,23=>65536,
        24=>65536,25=>65536,26=>65536,27=>65536,28=>65536,29=>65536,30=>65536,31=>65536,32=>65552,97=>195,98=>195,99=>195,100=>195,101=>195,
        102=>195,103=>195,104=>195,105=>195,106=>195,107=>195,108=>195,109=>195,110=>195,111=>195,112=>195,113=>195,114=>195,115=>195,116=>195,
        117=>195,118=>195,119=>195,120=>195,121=>195,122=>195,65=>195,66=>195,67=>195,68=>195,69=>195,70=>195,71=>195,72=>195,73=>195,74=>195,
        75=>195,76=>195,77=>195,78=>195,79=>195,80=>195,81=>195,82=>195,83=>195,84=>195,85=>195,86=>195,87=>195,88=>195,89=>195,90=>195,48=>197,
        49=>197,50=>197,51=>197,52=>197,53=>197,54=>197,55=>197,56=>197,57=>197,45=>129,34=>769,39=>257,46=>9,44=>9,33=>9,63=>9,58=>9,59=>9,
        60=>1025,62=>1025,91=>1025,93=>1025,123=>1025,125=>1025,40=>1025,41=>1025,64=>2049,35=>2049,36=>2049];

    /**
     * Qevix constructor
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->cfgReset();
        $this->setConfig($config);
    }

    /**
     * Установка конфигурации для одного или нескольких тегов
     *
     * @param array|string $tags тег(и)
     * @param int $flag флаг конфигурации
     * @param mixed $value значение флага
     * @param boolean $createIfNoExists создать запиcь о теге, если он ещё не определён
     */
    protected function _cfgSetTagsFlag($tags, $flag, $value, $createIfNoExists = true)
    {
        $tags = is_array($tags) ? $tags : [$tags];

        foreach($tags as $tag) {
            if(!$createIfNoExists && !isset($this->tagsRules[$tag])) {
                $this->tagsRules[$tag][self::TAG_ALLOWED] = false;
            }

            $this->tagsRules[$tag][$flag] = $value;
        }
    }

    /**
     * КОНФИГУРАЦИЯ: Задает список разрешенных тегов
     *
     * @param array|string $tags тег(и)
     * @param bool $reset нужно ли сбросить текущий список разрешенных тегов
     *
     * @return $this
     */
    public function cfgSetTagsAllowed($tags, $reset = false)
    {
        if ($reset) {
            foreach($this->tagsRules as $tag => $params) {
                $this->tagsRules[$tag][self::TAG_ALLOWED] = false;
            }
        }
        $this->_cfgSetTagsFlag($tags, self::TAG_ALLOWED, true);

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает, какие теги считать короткими (<br>, <img>)
     *
     * @param array|string $tags тег(и)
     *
     * @return $this
     */
    public function cfgSetTagShort($tags)
    {
        $this->_cfgSetTagsFlag($tags, self::TAG_SHORT, true, false);

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает преформатированные теги, в которых нужно всё заменять на HTML сущности
     *
     * @param array|string $tags тег(и)
     *
     * @return $this
     */
    public function cfgSetTagPreformatted($tags)
    {
        $this->_cfgSetTagsFlag($tags, self::TAG_PREFORMATTED, true, false);

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает теги в которых нужно отключить типографирование текста
     *
     * @param array|string $tags тег(и)
     *
     * @return $this
     */
    public function cfgSetTagNoTypography($tags)
    {
        $this->_cfgSetTagsFlag($tags, self::TAG_NO_TYPOGRAPHY, true, false);

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает не короткие теги, которые могут быть пустыми и их не нужно из-за этого удалять
     *
     * @param array|string $tags тег(и)
     *
     * @return $this
     */
    public function cfgSetTagIsEmpty($tags)
    {
        $this->_cfgSetTagsFlag($tags, self::TAG_EMPTY, true, false);

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает теги внутри которых не нужна авторасстановка тегов перевода на новую строку
     *
     * @param array|string $tags тег(и)
     *
     * @return $this
     */
    public function cfgSetTagNoAutoBr($tags)
    {
        $this->_cfgSetTagsFlag($tags, self::TAG_NO_AUTO_BR, true, false);

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает теги, которые необходимо вырезать вместе с содержимым (style, script, iframe)
     *
     * @param array|string $tags тег(и)
     *
     * @return $this
     */
    public function cfgSetTagCutWithContent($tags)
    {
        $this->_cfgSetTagsFlag($tags, self::TAG_CUT, true);

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает теги после которых не нужно добавлять дополнительный перевод строки,
     * например, блочные теги
     *
     * @param array|string $tags тег(и)
     *
     * @return $this
     */
    public function cfgSetTagBlockType($tags)
    {
        $this->_cfgSetTagsFlag($tags, self::TAG_BLOCK_TYPE, true, false);

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Добавляет разрешенные атрибуты для тегов
     *
     * @param string $tag тег
     * @param string|array $attrs разрешённые атрибуты
     *
     * @return $this
     */
    public function cfgSetTagAttrAllowed($tag, $attrs)
    {
        $attrs = is_array($attrs) ? $attrs : [$attrs];

        foreach($attrs as $key => $value) {
            if(is_string($key)) {
                $this->tagsRules[$tag][self::TAG_ATTR_ALLOWED][$key] = $value;
            } else {
                $this->tagsRules[$tag][self::TAG_ATTR_ALLOWED][$value] = '#text';
            }
        }
        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Добавляет обязательные атрибуты для тега
     *
     * @param string $tag тег
     * @param string|array $attrs разрешённые атрибуты
     *
     * @return $this
     */
    public function cfgSetTagAttrRequired($tag, $attrs)
    {
        $attrs = is_array($attrs) ? $attrs : [$attrs];
        foreach($attrs as $param) {
            $this->tagsRules[$tag][self::TAG_ATTR_REQUIRED][$param] = true;
        }
        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает значения по умолчанию для атрибутов тега
     *
     * @param string $tag тег
     * @param string $attr атрибут
     * @param string $value значение
     * @param boolean $isRewrite перезаписывать значение значением по умолчанию
     *
     * @return $this
     */
    public function cfgSetTagAttrDefault($tag, $attr, $value, $isRewrite = false)
    {
        $this->tagsRules[$tag][self::TAG_PARAM_AUTO_ADD][$attr] = ['value'=>$value, 'rewrite'=>$isRewrite];

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает, какие теги являются контейнерами для других тегов
     *
     * @param string $tag тег
     * @param string|array $childs разрешённые дочерние теги
     * @param boolean $isParentOnly тег является только контейнером других тегов и не может содержать текст
     * @param boolean $isChildOnly вложенные теги не могут присутствовать нигде кроме указанного тега
     *
     * @return $this
     */
    public function cfgSetTagChildren($tag, $childs, $isParentOnly = false, $isChildOnly = false)
    {
        $childs = is_array($childs) ? $childs : [$childs];

        if($isParentOnly) {
            $this->tagsRules[$tag][self::TAG_PARENT_ONLY] = true;
        }

        foreach($childs as $child) {
            $this->tagsRules[$tag][self::TAG_CHILD][$child] = true;
            $this->tagsRules[$child][self::TAG_PARENT][$tag] = true;

            if($isChildOnly) {
                $this->tagsRules[$child][self::TAG_CHILD_ONLY] = true;
            }
        }

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Указывает, какие теги не должны быть дочерними к другим тегам
     *
     * @param string|array $tags тег
     *
     * @return $this
     */
    public function cfgSetTagGlobal($tags)
    {
        $this->_cfgSetTagsFlag($tags, self::TAG_GLOBAL_ONLY, true, false);

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Устанавливает на тег callback-функцию для построения тега
     *
     * @param string $tag тег
     * @param mixed $callback функция
     *
     * @return $this
     */
    public function cfgSetTagBuildCallback($tag, $callback)
    {
        $this->tagsRules[$tag][self::TAG_BUILD_CALLBACK] = $callback;

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Устанавливает на тег callback-функцию для сбора информации
     *
     * @param string $tag тег
     * @param mixed $callback функция
     *
     * @return $this
     */
    public function cfgSetTagEventCallback($tag, $callback)
    {
        $this->tagsRules[$tag][self::TAG_EVENT_CALLBACK] = $callback;

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Устанавливает на строку предварённую спецсимволом callback-функцию
     *
     * @param string $char спецсимвол
     * @param mixed $callback функция
     *
     * @return $this
     *
     * @throws \RuntimeException
     */
    public function cfgSetSpecialCharCallback($char, $callback)
    {
        if(!is_string($char) || mb_strlen($char) !== 1) {
            throw new \RuntimeException('Параметр $char метода ' . __METHOD__ . ' должен быть строкой из одного символа');
        }

        $charClass = $this->getClassByOrd(static::ord($char));

        if(($charClass & self::SPECIAL_CHAR) === self::NIL) {
            throw new \RuntimeException('Параметр $char метода ' . __METHOD__ . ' отсутствует в списке разрешенных символов');
        }

        $this->isSpecialCharMode = true;

        $this->specialChars[$char] = $callback;

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Устанавливает список разрешенных протоколов для ссылок (https, http, ftp)
     *
     * @param array $protocols Список протоколов
     *
     * @return $this
     */
    public function cfgSetLinkProtocolAllow($protocols)
    {
        $protocols = is_array($protocols) ? $protocols : [$protocols];

        $this->linkProtocolAllowed = $protocols;

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Устанавливает протокол для ссылок по умолчанию
     *
     * @param string $protocol Протокол
     *
     * @return $this
     */
    public function cfgSetLinkDefaultProtocol($protocol)
    {
        $this->linkProtocolDefault = $protocol;

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Включает или выключает режим XHTML
     *
     * @param boolean $isXHTMLMode
     *
     * @return $this
     */
    public function cfgSetXHTMLMode($isXHTMLMode)
    {
        $isXHTMLMode = (bool)$isXHTMLMode;

        $this->br = $isXHTMLMode ? '<br/>' : '<br>';
        $this->isXHTMLMode = $isXHTMLMode;

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Включает или выключает режим автозамены символов переводов строк на тег <br>.
     * Задает так же, сохранять ли сам символ перевода строки и число переводов строки подряд
     *
     * @param boolean $isAutoBrMode
     * @param boolean $isSaveNl
     * @param int $limitNL
     *
     * @return $this
     */
    public function cfgSetAutoBrMode($isAutoBrMode, $isSaveNl = null, $limitNL = null)
    {
        if (null !== $isAutoBrMode) {
            $this->isAutoBrMode = (bool)$isAutoBrMode;
        }
        if (null !== $isSaveNl) {
            $this->isSaveNL = (bool)$isSaveNl;
        }
        if (null !== $limitNL) {
            $this->limitNL = (int)$limitNL;
        }

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Включает или выключает режим автоматического определения ссылок
     *
     * @param boolean $isAutoLinkMode
     *
     * @return $this
     */
    public function cfgSetAutoLinkMode($isAutoLinkMode)
    {
        $this->isAutoLinkMode = (bool)$isAutoLinkMode;

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Задает символ/символы перевода строки в готовом тексте (\n или \r\n)
     *
     * @param string $nl - "\n" или "\r\n", или null - присваивается автоматически
     *
     * @return $this
     */
    public function cfgSetEOL($nl)
    {
        if (null === $nl) {
            $this->nl = PHP_EOL;
        } elseif(in_array($nl, ["\n", "\r\n"])) {
            $this->nl = $nl;
        }

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Задает массив для автозамены
     *
     * @param array $replace
     *
     * @return $this
     */
    public function cfgSetAutoReplace($replace)
    {
        if (!empty($replace) && is_array($replace)) {
            if ($this->autoReplace) {
                foreach ($replace as $key => $val) {
                    $this->autoReplace[$key] = $val;
                }
            } else {
                $this->autoReplace = $replace;
            }
        }

        return $this;
    }

    /**
     * КОНФИГУРАЦИЯ: Задает режим работы парсера
     *
     * @param $mode
     *
     * @return $this
     */
    public function cfgSetMode($mode)
    {
        $mode = strtolower($mode);
        if (in_array($mode, ['text', 'html', 'xhtml'])) {
            $this->mode = $mode;
            if ($mode !== 'xhtml') {
                $this->cfgSetXHTMLMode(false);
            }
        }

        return $this;
    }

    /**
     * @deprecated
     *
     * @param array|string $tags тег(и)
     * @param bool $reset нужно ли сбросить текущий список разрешенных тегов
     *
     * @return $this
     */
    public function cfgAllowTags($tags, $reset = false)
    {
        return $this->cfgSetTagsAllowed($tags, $reset);
    }

    /**
     * @deprecated
     *
     * КОНФИГУРАЦИЯ: Алиас функции cfgAllowTagAttr($tag, $attrs)
     *
     * @param string $tag тег
     * @param string|array $attrs разрешённые атрибуты
     *
     * @return $this
     */
    public function cfgAllowTagParams($tag, $attrs)
    {
        return $this->cfgSetTagAttrAllowed($tag, $attrs);
    }

    /**
     * @deprecated
     *
     * КОНФИГУРАЦИЯ: Алиас функции cfgAllowTagAttributes($tag, $attrs)
     *
     * @param string $tag тег
     * @param string|array $attrs разрешённые атрибуты
     *
     * @return $this
     */
    public function cfgSetTagParamsRequired($tag, $attrs)
    {
        return $this->cfgSetTagAttrRequired($tag, $attrs);
    }

    /**
     * @deprecated
     *
     * КОНФИГУРАЦИЯ: Указывает значения по умолчанию для атрибутов тега
     *
     * @param string $tag тег
     * @param string $attr атрибут
     * @param string $value значение
     * @param boolean $isRewrite перезаписывать значение значением по умолчанию
     *
     * @return $this
     */
    public function cfgSetTagParamDefault($tag, $attr, $value, $isRewrite = false)
    {
        return $this->cfgSetTagAttrDefault($tag, $attr, $value, $isRewrite);
    }

    /**
     * @deprecated
     *
     * КОНФИГУРАЦИЯ: Указывает, какие теги являются контейнерами для других тегов
     *
     * @param string $tag тег
     * @param string|array $childs разрешённые дочерние теги
     * @param boolean $isParentOnly тег является только контейнером других тегов и не может содержать текст
     * @param boolean $isChildOnly вложенные теги не могут присутствовать нигде кроме указанного тега
     *
     * @return $this
     */
    public function cfgSetTagChilds($tag, $childs, $isParentOnly = false, $isChildOnly = false)
    {
        return $this->cfgSetTagChildren($tag, $childs, $isParentOnly, $isChildOnly);
    }

    /**
     * @return $this
     */
    public function cfgReset()
    {
        $this->tagsRules   = [];
        $this->entities    = ['"' => '&#34;', "'" => '&#39;', '<' => '&#60;', '>' => '&#62;', '&' => '&#38;'];
        $this->quotes      = [['«', '»'], ['„', '“']];
        $this->bracketsALL = ['<' => '>', '[' => ']', '{' => '}', '(' => ')'];
        $this->bracketsSPC = ['[' => ']', '{' => '}'];
        $this->dash        = '—';
        $this->nl          = "\n";
        $this->autoReplace = [];
        $this->linkProtocolAllowed = ['https', 'http', 'ftp'];
        $this->linkProtocolDefault = 'https';

        return $this;
    }

    /**
     * @param array $config
     * @param bool $reset
     *
     * @return $this
     */
    public function setConfig(array $config, $reset = false)
    {
        if ($reset) {
            $this->cfgReset();
        }
        foreach($config as $section => $data) {
            if ($section === 'forbidden_tags' || $section === 'allowed_tags') {
                // эти параметры обрабатываем после цикла в последнюю очередь
                continue;
            }
            if ($section === 'auto_replace') {
                $this->cfgSetAutoReplace($data);
            }
            elseif ($section === 'links') {
                // Включает или выключает режим автоматического определения ссылок
                if (isset($data['auto'])) {
                    $this->cfgSetAutoLinkMode($data['auto']);
                }
                // Устанавливает список разрешенных протоколов для ссылок (https, http, ftp)
                if (!empty($data['protocols'])) {
                    $this->cfgSetLinkProtocolAllow($data['protocols']);
                }
            }
            elseif ($section === 'eol') {
                // Включает или выключает режим автозамены символов переводов строк на тег br
                if (isset($data['auto_br'])) {
                    $this->cfgSetAutoBrMode($data['auto_br'], isset($data['save']) ? $data['save'] : null, isset($data['limit']) ? $data['limit'] : null);
                }
                // Задает символ/символы перевода строки. По умолчанию "\n". Разрешено "\n" или "\r\n"
                if (isset($data['char'])) {
                    $this->cfgSetEOL($data['char']);
                }
            }
            elseif ($section === 'tags') {
                foreach($data as $tag => $params) {
                    $this->cfgSetTagsAllowed($tag);
                    foreach($params as $key => $val) {
                        // значения вида [0 => 'short'] приводим к виду ['short' => true]
                        if (is_int($key) && is_string($val)) {
                            $params[$val] = true;
                        }
                    }
                    // Указывает, какие теги считать короткими (<br>, <img>)
                    if (!empty($params['short'])) {
                        $this->cfgSetTagShort([$tag]);
                    }
                    // Указывает преформатированные теги, в которых нужно всё заменять на HTML сущности
                    if (!empty($params['pre'])) {
                        $this->cfgSetTagPreformatted([$tag]);
                    }
                    // Указывает не короткие теги, которые могут быть пустыми и их не нужно из-за этого удалять
                    if (!empty($params['empty'])) {
                        $this->cfgSetTagIsEmpty([$tag]);
                    }
                    // Указывает теги, внутри которых не нужна авто-расстановка тегов перевода на новую строку
                    if (!empty($params['no_auto_br'])) {
                        $this->cfgSetTagNoAutoBr([$tag]);
                    }
                    // Указывает теги, после которых не нужно добавлять дополнительный перевод строки. Например, блочные теги
                    if (!empty($params['block'])) {
                        $this->cfgSetTagBlockType([$tag]);
                    }
                    // Добавляет разрешенные атрибуты для тегов
                    if (!empty($params['attr'])) {
                        $attr = [];
                        foreach($params['attr'] as $key => $val) {
                            // атрибут без шаблона значений
                            if (is_int($key) && is_string($val)) {
                                if (strpos($val, '!') === 0) {
                                    $attr[] = substr($val, 1);
                                } else {
                                    $attr[] = $val;
                                }
                            } elseif (is_string($key)) {
                                if (strpos($key, '!') === 0) {
                                    $attr[substr($key, 1)] = $val;
                                } else {
                                    $attr[$key] = $val;
                                }
                            }
                        }
                        $this->cfgSetTagAttrAllowed($tag, $attr);
                    }
                    // Добавляет обязательные атрибуты для тега
                    if (!empty($params['required'])) {
                        $this->cfgSetTagAttrRequired($tag, $params['required']);
                    }
                    // Устанавливаем атрибуты тегов, которые будут добавляться автоматически
                    if (!empty($params['auto'])) {
                        foreach($params['auto'] as $key => $val) {
                            // атрибут без значения, значит, пустая строка
                            if (is_int($key) && is_string($val)) {
                                $key = $val;
                                $val = '';
                            }
                            // если массив, то [значение_по_умолчанию, надо_ли_перезаписывать]
                            if (is_array($val)) {
                                $val = $val[0];
                                $overwrite = (bool)$val[1];
                            } else {
                                $overwrite = false;
                            }
                            $this->cfgSetTagAttrDefault($tag, $key, $val, $overwrite);
                        }
                    }

                    // Указывает, какие теги являются контейнерами для других тегов
                    if (!empty($params['children'])) {
                        $noText = !empty($params['no_text']);
                        foreach($params['children'] as $child => $isChildOnly) {
                            if (is_int($child)) {
                                $child = $isChildOnly;
                                $isChildOnly = false;
                            }
                            $this->cfgSetTagChildren($tag, $child, $noText, $isChildOnly);
                        }
                    }
                    // Указывает, какие теги не должны быть дочерними к другим тегам
                    if (!empty($params['root'])) {
                        $this->cfgSetTagGlobal($tag);
                    }
                    // Указывает теги, в которых нужно отключить типографирование текста
                    if (!empty($params['no_typografy'])) {
                        $this->cfgSetTagNoTypography([$tag]);
                    }
                }
            }
            elseif ($section === 'callbacks') {
                if (!empty($data['tags'])) {
                    // Устанавливает на тег callback-функцию
                    foreach($data['tags'] as $tag => $callback) {
                        $this->cfgSetTagBuildCallback($tag, $callback);
                    }
                }
                if (!empty($data['chars'])) {
                    // Устанавливает на строку предворенную спецсимволом (@|#|$) callback-функцию
                    foreach($data['chars'] as $char => $callback) {
                        $this->cfgSetSpecialCharCallback($char, $callback);
                    }
                }
                if (!empty($data['event'])) {
                    // Устанавливает на тег callback-функцию, которая сохраняет URL изображений для meta-описания
                    foreach($data['event'] as $char => $callback) {
                        $this->cfgSetTagEventCallback($char, $callback);
                    }
                }
            }
            elseif ($section === 'mode') {
                $this->cfgSetMode($data);
            }
        }
        if (isset($config['allowed_tags'])) {
            // Задает список разрешенных тегов
            $this->cfgSetTagsAllowed($config['allowed_tags'], true);
        }
        if (isset($config['forbidden_tags'])) {
            // Указывает теги, которые необходимо вырезать вместе с содержимым
            $this->cfgSetTagCutWithContent($config['forbidden_tags']);
        }

        return $this;
    }

    /**
     * Разбивает строку в массив посимвольно
     *
     * @param string $str текст
     *
     * @return mixed
     */
    protected function strToArray($str)
    {
        preg_match_all('#.#su', $str, $chars); // preg_split работает медленнее

        return $chars[0];
    }

    /**
     * Получение следующего символа из входной строки
     *
     * @return boolean
     */
    protected function moveNextPos()
    {
        return $this->movePos($this->curPos + 1);
    }

    /**
     * Получение следующего символа из входной строки
     *
     * @return boolean
     */
    protected function movePrevPos()
    {
        return $this->movePos($this->curPos - 1);
    }

    /**
     * Перемещает указатель на указанную позицию во входной строке и считывание символа
     *
     * @param int $position позиция в тексте
     *
     * @return boolean
     */
    protected function movePos($position)
    {
        $prevPos = $position - 1;
        $curPos = $position;
        $nextPos = $position + 1;

        $prevPosStatus = ($prevPos < $this->textLen && $prevPos >= 0);

        $this->prevPos = $prevPos;
        $this->prevChar = $prevPosStatus ? $this->textBuf[$prevPos] : null;
        $this->prevCharOrd = $prevPosStatus ? static::ord($this->prevChar) : 0;
        $this->prevCharClass = $prevPosStatus ? $this->getClassByOrd($this->prevCharOrd) : self::NIL;

        $curPosStatus = ($curPos < $this->textLen && $curPos >= 0);

        $this->curPos = $curPos;
        $this->curChar = $curPosStatus ? $this->textBuf[$curPos] : null;
        $this->curCharOrd = $curPosStatus ? static::ord($this->curChar) : 0;
        $this->curCharClass = $curPosStatus ? $this->getClassByOrd($this->curCharOrd) : self::NIL;

        $nextPosStatus = ($nextPos < $this->textLen && $nextPos >= 0);

        $this->nextPos = $nextPos;
        $this->nextChar = $nextPosStatus ? $this->textBuf[$nextPos] : null;
        $this->nextCharOrd = $nextPosStatus ? static::ord($this->nextChar) : 0;
        $this->nextCharClass = $nextPosStatus ? $this->getClassByOrd($this->nextCharOrd) : self::NIL;

        return ($this->curChar !== null);
    }

    /**
     * Сохраняет текущее состояние автомата
     *
     */
    protected function saveState()
    {
        $state = [];

        $state['prevPos'] = $this->prevPos;
        $state['prevChar'] = $this->prevChar;
        $state['prevCharOrd'] = $this->prevCharOrd;
        $state['prevCharClass'] = $this->prevCharClass;

        $state['curPos'] = $this->curPos;
        $state['curChar'] = $this->curChar;
        $state['curCharOrd'] = $this->curCharOrd;
        $state['curCharClass'] = $this->curCharClass;

        $state['nextPos'] = $this->nextPos;
        $state['nextChar'] = $this->nextChar;
        $state['nextCharOrd'] = $this->nextCharOrd;
        $state['nextCharClass'] = $this->nextCharClass;

        $this->statesStack[] = $state;
    }

    /**
     * Восстанавливает последнее сохраненное состояние автомата
     *
     */
    protected function restoreState()
    {
        $state = array_pop($this->statesStack);

        $this->prevPos = $state['prevPos'];
        $this->prevChar = $state['prevChar'];
        $this->prevCharOrd = $state['prevCharOrd'];
        $this->prevCharClass = $state['prevCharClass'];

        $this->curPos = $state['curPos'];
        $this->curChar = $state['curChar'];
        $this->curCharOrd = $state['curCharOrd'];
        $this->curCharClass = $state['curCharClass'];

        $this->nextPos = $state['nextPos'];
        $this->nextChar = $state['nextChar'];
        $this->nextCharOrd = $state['nextCharOrd'];
        $this->nextCharClass = $state['nextCharClass'];
    }

    /**
     * Удаляет последнее сохраненное состояние
     *
     */
    protected function removeState()
    {
        array_pop($this->statesStack);
    }

    /**
     * Проверяет допустимость тега, классификатора тега и других параметров тега
     *
     */
    protected function tagsRules()
    {
        $args_list = func_get_args();

        if(count($args_list) === 0) {
            return false;
        }

        $tagsRules =& $this->tagsRules;
        foreach($args_list as $value) {
            if($value === null || !isset($tagsRules[$value])) {
                return false;
            }

            $tagsRules =& $tagsRules[$value];
        }

        return true;
    }

    /**
     * Проверяет точное вхождение символа в текущей позиции
     *
     * @param string $char символ
     *
     * @return boolean
     */
    protected function matchChar($char)
    {
        return ($this->curChar === $char);
    }

    /**
     * Проверяет вхождение символа указанного класса в текущей позиции
     *
     * @param int $charClass класс символа
     *
     * @return boolean
     */
    protected function matchCharClass($charClass)
    {
        return (bool)($this->curCharClass & $charClass);
    }

    /**
     * Проверяет точное вхождение кода символа в текущей позиции
     *
     * @param int $charOrd код символа
     *
     * @return boolean
     */
    protected function matchCharOrd($charOrd)
    {
        return $this->curCharOrd === $charOrd;
    }

    /**
     * Проверяет точное совпадение строки в текущей позиции
     *
     * @param string $str
     *
     * @return boolean
     */
    protected function matchStr($str)
    {
        $this->saveState();
        $length = mb_strlen($str, 'UTF-8');
        $buffer = '';

        while($length-- && $this->curCharClass)
        {
            $buffer .= $this->curChar;
            $this->moveNextPos();
        }

        $this->restoreState();

        return $buffer === $str;
    }

    /**
     * Пропускает текст до нахождения указанного символа
     *
     * @param string $char символ для поиска
     *
     * @return boolean
     */
    protected function skipTextToChar($char)
    {
        while($this->curChar !== $char && $this->curCharClass)
        {
            $this->moveNextPos();
        }

        return $this->curCharClass ? true : false;
    }

    /**
     * Пропускает текст до нахождения указанной строки
     *
     * @param string $str строка или символ для поиска
     *
     * @return boolean
     */
    protected function skipTextToStr($str)
    {
        $chars = $this->strToArray($str);

        while($this->curCharClass) {
            if($this->curChar === $chars[0]) {
                $this->saveState();

                $state = true;
                foreach($chars as $char) {
                    if($this->curCharClass === self::NIL) {
                        $this->removeState();
                        return false;
                    }

                    if($this->curChar !== $char) {
                        $state = false;
                        break;
                    }

                    $this->moveNextPos();
                }

                $this->restoreState();

                if($state) {
                    return true;
                }
            }

            $this->moveNextPos();
        }

        return false;
    }

    /**
     * Пропускает строку если она начинается с текущей позиции
     *
     * $this->skipTextToStr('-->') && $this->skipStr('-->');
     *
     * @param string $str строка для пропуска
     *
     * @return boolean
     */
    protected function skipStr($str)
    {
        $chars = $this->strToArray($str);

        $this->saveState();

        $state = true;
        foreach($chars as $char) {
            if($this->curCharClass === self::NIL) {
                $state = false;
                break;
            }

            if($this->curChar !== $char) {
                $state = false;
                break;
            }

            $this->moveNextPos();
        }

        if($state) {
            $this->removeState();
        }
        else {
            $this->restoreState();
        }

        return $state ? true : false;
    }

    /**
     * Возвращает класс символа по его коду
     *
     * @param int $ord код символа
     *
     * @return int класс символа
     */
    protected function getClassByOrd($ord)
    {
        return isset($this->charClasses[$ord]) ? $this->charClasses[$ord] : self::PRINTABLE;
    }

    /**
     * Пропускает пробелы
     *
     * @return int количество пропусков
     */
    protected function skipSpaces()
    {
        $count = 0;
        while($this->curCharClass & self::SPACE) {
            $this->moveNextPos();
            $count++;
        }
        return $count;
    }

    /**
     * Пропускает символы перевода строк
     *
     * @param int $limit лимит пропусков символов перевода строк, при установке в 0 - не лимитируется
     *
     * @return boolean
     */
    protected function skipNL($limit=0)
    {
        $count = 0;
        while($this->curCharClass & self::NL) {
            if($limit > 0 && $count >= $limit) {
                break;
            }

            $this->moveNextPos();
            $this->skipSpaces();

            $count++;
        }

        return $count;
    }

    /**
     * Пропускает символы относящиеся к классу и возвращает кол-во пропущенных символов
     *
     * @param int $class класс для пропуска
     *
     * @return string
     */
    protected function skipClass($class)
    {
        $count = 0;
        while($this->curCharClass & $class) {
            $this->moveNextPos();
            $count++;
        }

        return $count;
    }

    /**
     * Захватывает все последующие символы относящиеся к классу и возвращает их
     *
     * @param int $class класс для захвата
     *
     * @return string
     */
    protected function grabCharClass($class)
    {
        $result = '';
        while($this->curCharClass & $class) {
            $result .= $this->curChar;
            $this->moveNextPos();
        }

        return $result;
    }

    /**
     * Захватывает все последующие символы НЕ относящиеся к классу и возвращает их
     *
     * @param int $class класс для остановки захвата
     *
     * @return string
     */
    protected function grabNotCharClass($class)
    {
        $result = '';
        while($this->curCharClass && ($this->curCharClass & $class) === self::NIL) {
            $result .= $this->curChar;
            $this->moveNextPos();
        }

        return $result;
    }

    /**
     * Готовит контент
     *
     * @param string|null $parentTag имя родительского тега
     *
     * @return string
     */
    protected function makeContent($parentTag = null)
    {
        $content = '';

        $this->skipSpaces();
        $this->skipNL();

        while($this->curCharClass) {
            $tagName = null;
            $tagParams = [];
            $tagContent = null;
            $shortTag = false;

            // Если текущий тег это тег без текста - пропускаем символы до "<"
            if($this->curChar !== '<' && $this->tagsRules($this->curTag, self::TAG_PARENT_ONLY)) {
                $this->skipTextToChar('<');
            }

            $this->saveState();

            if ($this->curChar === '<') {
                // Тег в котором есть текст
                if($this->matchTag($tagName, $tagParams, $tagContent, $shortTag)) {
                    $tagBuilt = $this->makeTag($tagName, $tagParams, $tagContent, $shortTag, $parentTag);
                    $content .= $tagBuilt;

                    if($tagBuilt !== '' && ($this->tagsRules($tagName, self::TAG_BLOCK_TYPE) || $tagName === 'br')) {
                        $this->skipNL(1);
                    }

                    if($tagBuilt === '') {
                        $this->skipClass(self::SPACE | self::NL);
                    }
                }
                // Комментарий <!-- -->
                else if($this->matchStr('<!--')) {
                    $this->skipTextToStr('-->') && $this->skipStr('-->');
                    $this->skipClass(self::SPACE | self::NL);
                }
                // Конец тега
                else if($this->matchTagClose($tagName)) {
                    if($this->curTag !== null) {
                        $this->restoreState();
                        return $content;
                    }
                    $this->setError('Не ожидалось закрывающего тега ' . $tagName);
                }
                // Просто символ "<"
                else {
                    if(!$this->tagsRules($this->curTag, self::TAG_PARENT_ONLY)) {
                        $content .= $this->entities['<'];
                    }
                    $this->moveNextPos();
                }
            }

            // Наверно тут просто текст, формируем его
            else {
                $content .= $this->makeText();
            }

            $this->removeState();
        }

        return $content;
    }

    /**
     * Обработка тега полностью
     *
     * @param string|null $tagName имя тега
     * @param array $tagParams параметры тега
     * @param string $tagContent контент тега
     * @param boolean $shortTag короткий ли тег
     *
     * @return boolean
     */
    protected function matchTag(&$tagName, &$tagParams, &$tagContent, &$shortTag)
    {
        $tagName = null;
        $tagParams = [];
        $tagContent = '';
        $shortTag = false;
        $closeTag = null;

        if(!$this->matchTagOpen($tagName, $tagParams, $shortTag)) {
            return false;
        }

        if($shortTag) {
            return true;
        }

        $curTag = $this->curTag;
        $typoMode = $this->typoMode;

        if($this->tagsRules($tagName, self::TAG_NO_TYPOGRAPHY)) {
            $this->typoMode = false;
        }

        $this->curTag = $tagName;

        if($this->tagsRules($tagName, self::TAG_PREFORMATTED)) {
            $tagContent = $this->makePreformatted($tagName);
        }
        else {
            $tagContent = $this->makeContent($tagName);
        }

        if(($tagName !== $closeTag) && $this->matchTagClose($closeTag)) {
            $this->setError('Неверный закрывающий тег ' . $closeTag . '. Ожидалось закрытие ' . $tagName . '');
        }

        $this->curTag = $curTag;
        $this->typoMode = $typoMode;

        return true;
    }

    /**
     * Обработка открывающего тега
     *
     * @param string $tagName имя тега
     * @param array $tagParams параметры тега
     * @param boolean $shortTag короткий ли тег
     *
     * @return boolean
     */
    protected function matchTagOpen(&$tagName, &$tagParams, &$shortTag)
    {
        if($this->curChar !== '<') {
            return false;
        }

        $this->saveState();

        $this->skipSpaces() || $this->moveNextPos();

        $tagName = $this->grabCharClass(self::TAG_NAME);

        $this->skipSpaces();

        if($tagName === '') {
            $this->restoreState();
            return false;
        }

        $tagName = mb_strtolower($tagName, 'UTF-8');

        if($this->curChar !== '>' && $this->curChar !== '/') {
            $this->matchTagParams($tagParams);
        }

        $shortTag = $this->tagsRules($tagName, self::TAG_SHORT);

        if(!$shortTag && $this->curChar === '/') {
            $this->restoreState();
            return false;
        }

        if($shortTag && $this->curChar === '/') {
            $this->moveNextPos();
        }

        $this->skipSpaces();

        if($this->curChar !== '>') {
            $this->restoreState();
            return false;
        }

        $this->removeState();
        $this->moveNextPos();

        return true;
    }

    /**
     * Обработка закрывающего тега
     *
     * @param string $tagName имя тега
     *
     * @return boolean
     */
    protected function matchTagClose(&$tagName)
    {
        if($this->curChar !== '<') {
            return false;
        }

        $this->saveState();

        $this->skipSpaces() || $this->moveNextPos();

        if($this->curChar !== '/') {
            $this->restoreState();
            return false;
        }

        $this->skipSpaces() || $this->moveNextPos();

        $tagName = $this->grabCharClass(self::TAG_NAME);

        $this->skipSpaces();

        if($tagName === '' || $this->curChar !== '>') {
            $this->restoreState();
            return false;
        }

        $tagName = mb_strtolower($tagName, 'UTF-8');

        $this->removeState();
        $this->moveNextPos();

        return true;
    }

    /**
     * Обработка параметров тега
     *
     * @param array $params массив параметров
     *
     * @return boolean
     */
    protected function matchTagParams(&$params)
    {
        $name = null;
        $value = null;

        while($this->matchTagParam($name, $value)) {
            if(mb_strpos($name, '-', 0, 'UTF-8') !== 0) {
                $params[$name] = $value;
            }
            $name = $value = '';
        }

        return count($params) > 0;
    }

    /**
     * Обработка одного параметра тега
     *
     * @param string $name имя параметра
     * @param string $value значение параметра
     *
     * @return boolean
     */
    protected function matchTagParam(&$name, &$value)
    {
        $this->saveState();
        $this->skipSpaces();

        $name = $this->grabCharClass(self::TAG_PARAM_NAME);

        if($name === '') {
            $this->removeState();
            return false;
        }

        $this->skipSpaces();

        // Параметр без значения
        if($this->curChar !== '=') {
            if($this->curChar === '>' || $this->curChar === '/' || (($this->curCharClass & self::TAG_PARAM_NAME) && $this->curChar !== '-')) {
                $value = '';

                $this->removeState();
                return true;
            }
            $this->restoreState();
            return false;
        }
        $this->moveNextPos();

        $this->skipSpaces();

        if(!$this->matchTagParamValue($value)) {
            $this->restoreState();
            return false;
        }

        $this->skipSpaces();
        $this->removeState();

        return true;
    }

    /**
     * Обработка значения параметра тега
     *
     * @param string $value значение параметра
     *
     * @return boolean
     */
    protected function matchTagParamValue(&$value)
    {
        if($this->curCharClass & self::TAG_QUOTE) {
            $quote = $this->curChar;
            $escape = false;

            $this->moveNextPos();

            while($this->curCharClass && ($this->curChar !== $quote || $escape === true)) {
                $value .= isset($this->entities[$this->curChar]) ? $this->entities[$this->curChar] : $this->curChar;

                // Возможны экранированные кавычки
                $escape = $this->curChar === '\\';

                $this->moveNextPos();
            }

            if($this->curChar !== $quote) {
                return false;
            }

            $this->moveNextPos();
        }
        else {
            while($this->curCharClass && ($this->curCharClass & self::SPACE) === self::NIL && $this->curChar !== '>') {
                $value .= isset($this->entities[$this->curChar]) ? $this->entities[$this->curChar] : $this->curChar;
                $this->moveNextPos();
            }
        }

        return true;
    }

    /**
     * Готовит преформатированный контент
     *
     * @param string $openTag текущий открывающий тег
     *
     * @return string
     */
    protected function makePreformatted($openTag = null)
    {
        $content = '';

        while($this->curCharClass) {
            if($this->curChar === '<' && $openTag !== null) {
                $closeTag = '';
                $this->saveState();

                $isClosedTag = $this->matchTagClose($closeTag);

                if($isClosedTag) {
                    $this->restoreState();
                }
                else {
                    $this->removeState();
                }

                if($isClosedTag && $openTag === $closeTag) {
                    break;
                }
            }

            $content .= isset($this->entities[$this->curChar]) ? $this->entities[$this->curChar] : $this->curChar;

            $this->moveNextPos();
        }

        return $content;
    }

    /**
     * Готовит тег к печати
     *
     * @param string $tagName имя тега
     * @param array $tagParams параметры тега
     * @param string $tagContent контент тега
     * @param boolean $shortTag короткий ли тег
     * @param string $parentTag имя тега родителя, если есть
     *
     * @return boolean
     */
    protected function makeTag($tagName, $tagParams, $tagContent, $shortTag, $parentTag = null)
    {
        $text = '';
        $tagName = mb_strtolower($tagName, 'UTF-8');

        // Тег необходимо вырезать вместе с содержимым
        if($this->tagsRules($tagName, self::TAG_CUT)) {
            return '';
        }

        // Допустим ли тег к использованию
        if(!$this->tagsRules($tagName, self::TAG_ALLOWED)) {
            return $this->tagsRules($parentTag, self::TAG_PARENT_ONLY) ? '' : $tagContent;
        }

        // Должен ли тег НЕ быть дочерним к любому другому тегу
        if($this->tagsRules($tagName, self::TAG_GLOBAL_ONLY) && $parentTag !== null) {
            return $tagContent;
        }

        // Может ли тег находиться внутри родительского тега
        if($this->tagsRules($parentTag, self::TAG_PARENT_ONLY) && !$this->tagsRules($parentTag, self::TAG_CHILD, $tagName)) {
            return '';
        }

        // Тег может находиться только внутри другого тега
        if($this->tagsRules($tagName, self::TAG_CHILD_ONLY) && !$this->tagsRules($tagName, self::TAG_PARENT, $parentTag)) {
            return $tagContent;
        }

        // Параметры тега
        $tagParamsResult = [];
        foreach($tagParams as $param => $value) {
            $param = mb_strtolower($param, 'UTF-8');
            $value = trim($value);

            // Разрешен ли этот атрибут
            $paramAllowedValues = $this->tagsRules($tagName, self::TAG_ATTR_ALLOWED, $param) ? $this->tagsRules[$tagName][self::TAG_ATTR_ALLOWED][$param] : false;

            if($paramAllowedValues === false) {
                continue;
            }

            // Параметр есть в списке и это массив возможных значений
            if(is_array($paramAllowedValues)) {
                if(isset($paramAllowedValues['#link']) && is_array($paramAllowedValues['#link'])) {
                    if(preg_match('#javascript:#iu', $value)) {
                        $this->setError('Попытка вставить JavaScript в URI');
                        continue;
                    }

                    $protocols = implode('|', array_map(function($item){
                        return $item . ':';
                    }, $this->linkProtocolAllowed));

                    $found = false;
                    foreach($paramAllowedValues['#link'] as $domain) {
                        $domain = preg_quote($domain, '#');
                        // (http:|https:)? или то, или то, или ничего
                        if(preg_match('#^(' . $protocols . ')?//' . $domain . '(/|$)#iu', $value)) {
                            $found = true;
                            break;
                        }
                    }
                    if(!$found) {
                        $this->setError('Недопустимое значение "' . $value . '" для атрибута "' . $param . '" тега "' . $tagName . '"');
                        continue;
                    }
                }
                else if(!in_array($value, $paramAllowedValues, true)) {
                    $this->setError('Недопустимое значение "'.$value.'" для атрибута "'.$param.'" тега "'.$tagName.'"');
                    continue;
                }
            }

            // Параметр есть в списке и это строка представляющая правило
            if(is_string($paramAllowedValues)) {
                if($paramAllowedValues === '#int') {
                    if(!preg_match('#^\d+$#u', $value)) {
                        $this->setError('Недопустимое значение "' . $value . '" для атрибута "' . $param . '" тега "' . $tagName . '". Ожидалось число');
                        continue;
                    }
                }

                else if($paramAllowedValues === '#text') {
                    // ничего не делаем
                }

                else if($paramAllowedValues === '#bool') {
                    $value = null;
                }

                else if($paramAllowedValues === '#link') {
                    if(preg_match('#javascript:#iu', $value)) {
                        $this->setError('Попытка вставить JavaScript в URI');
                        continue;
                    }

                    if(!preg_match('#^[a-z0-9\/\#]#iu', $value)) {
                        $this->setError('Первый символ URL должен быть буквой, цифрой, символами слеша или решетки');
                        continue;
                    }

                    $protocols = implode('|', array_map(static function($item){
                        return $item . ':';
                    }, $this->linkProtocolAllowed));

                    // (http:|https:)? или то, или то, или ничего
                    if(!preg_match('#^(' . $protocols . ')?\/\/#iu', $value) && !preg_match('#^(\/|\#)#u', $value) && !preg_match('#^mailto:#iu', $value)) {
                        $value = $this->linkProtocolDefault . '://' . $value;
                    }
                }

                else if(strpos($paramAllowedValues, '#regexp') === 0) {
                    if(preg_match('#^\#regexp\((.*?)\)$#iu', $paramAllowedValues, $match)) {
                        if(!preg_match('#^'.$match[1].'$#iu', $value)) {
                            $this->setError('Недопустимое значение "' . $value . '" для атрибута "' . $param . '" тега "' . $tagName . '". Ожидалась строка подходящая под регулярное выражение "' . $match[1] . '"');
                            continue;
                        }
                    } else {
                        $this->setError('Недопустимое значение "'.$value.'" для атрибута "'.$param.'" тега "'.$tagName.'". Ожидалось "'.$paramAllowedValues.'"');
                        continue;
                    }
                }

                else if($paramAllowedValues !== $value) {
                    $this->setError('Недопустимое значение "' . $value . '" для атрибута "' . $param . '" тега "' . $tagName . '". Ожидалось "' . $paramAllowedValues.'"');
                    continue;
                }
            }

            $tagParamsResult[$param] = $value;
        }

        // Проверка обязательных параметров тега
        $requiredParams = $this->tagsRules($tagName, self::TAG_ATTR_REQUIRED) ? array_keys($this->tagsRules[$tagName][self::TAG_ATTR_REQUIRED]) : [];

        foreach($requiredParams as $requiredParam) {
            if(!isset($tagParamsResult[$requiredParam])) {
                return $tagContent;
            }
        }

        // Авто добавляемые параметры
        if($this->tagsRules($tagName, self::TAG_PARAM_AUTO_ADD)) {
            foreach($this->tagsRules[$tagName][self::TAG_PARAM_AUTO_ADD] as $param => $value) {
                if(!isset($tagParamsResult[$param]) || $value['rewrite']) {
                    $tagParamsResult[$param] = $value['value'];
                }
            }
        }

        // Удаляем пустые не короткие теги если не сказано другого
        if(!$this->tagsRules($tagName, self::TAG_EMPTY)) {
            if(!$shortTag && $tagContent === '') {
                return '';
            }
        }

        // Вызываем callback функцию event... перед сборкой тега
        if($this->tagsRules($tagName, self::TAG_EVENT_CALLBACK)) {
            call_user_func($this->tagsRules[$tagName][self::TAG_EVENT_CALLBACK], $tagName, $tagParamsResult, $tagContent);
        }

        // Вызываем callback функцию, если тег собирается именно так
        if($this->tagsRules($tagName, self::TAG_BUILD_CALLBACK)) {
            return call_user_func($this->tagsRules[$tagName][self::TAG_BUILD_CALLBACK], $tagName, $tagParamsResult, $tagContent);
        }

        // Собираем тег
        $text .= '<'.$tagName;

        foreach($tagParamsResult as $param => $value) {
            if ($value === null) {
                if ($this->isXHTMLMode) {
                    $text .= ' ' . $param . '="' . $param . '"';
                } else {
                    $text .= ' ' . $param;
                }
            } else {
                $text .= ' ' . $param . '="' . $value . '"';
            }
        }

        $text .= ($shortTag && $this->isXHTMLMode) ? '/>' : '>';

        if($this->tagsRules($tagName, self::TAG_PARENT_ONLY)) {
            $text .= "\n";
        }

        if(!$shortTag) {
            $text .= $tagContent.'</'.$tagName.'>';
        }

        if($this->tagsRules($parentTag, self::TAG_PARENT_ONLY)) {
            $text .= "\n";
        }

        if($this->tagsRules($tagName, self::TAG_BLOCK_TYPE)) {
            $text .= "\n";
        }

        if($tagName === 'br') {
            $text .= "\n";
        }

        return $text;
    }

    /**
     * Проверяет текущую позицию на вхождение тире пригодного для замены
     *
     * @param string $dash тире
     *
     * @return boolean
     */
    protected function matchDash(&$dash = '')
    {
        if($this->curChar !== '-') {
            return false;
        }

        if(($this->prevCharClass & (self::SPACE | self::NL | self::TEXT_BRACKET)) === self::NIL && $this->prevCharClass !== self::NIL) {
            return false;
        }

        $this->saveState();

        while($this->nextChar === '-') {
            $this->moveNextPos();
        }

        if(($this->nextCharClass & (self::SPACE | self::NL | self::TEXT_BRACKET)) === self::NIL && $this->nextCharClass !== self::NIL) {
            $this->restoreState();
            return false;
        }

        $dash = $this->dash;
        $this->removeState();
        $this->moveNextPos();

        return true;
    }

    /**
     * Определяет HTML сущности
     *
     * @param string $entity сущность
     *
     * @return boolean
     */
    protected function matchHTMLEntity(&$entity = '')
    {
        if($this->curChar !== '&') {
            return '';
        }

        $this->saveState();
        $this->moveNextPos();

        if($this->curChar === '#') {
            $this->moveNextPos();

            $entityCode = $this->grabCharClass(self::NUMERIC);

            if($entityCode === '' || $this->curChar !== ';') {
                $this->restoreState();
                return false;
            }
            $this->removeState();
            $this->moveNextPos();

            $entity = html_entity_decode("&#".$entityCode.";", ENT_COMPAT, 'UTF-8');

            return true;
        }

        $entityName = $this->grabCharClass(self::ALPHA | self::NUMERIC);

        if($entityName === '' || $this->curChar !== ';') {
            $this->restoreState();
            return false;
        }

        $this->removeState();
        $this->moveNextPos();

        $entity = html_entity_decode('&' . $entityName . ';', ENT_COMPAT, 'UTF-8');

        return true;
    }

    /**
     * Проверяет текущую позицию на вхождение кавычки пригодной для замены
     *
     * @param string $quote кавычка
     *
     * @return boolean
     */
    protected function matchQuote(&$quote = '')
    {
        if(($this->curCharClass & self::TEXT_QUOTE) === self::NIL) {
            return false;
        }

        $type = ($this->quotesOpened >= 2) ||
        ($this->quotesOpened > 0 &&
            ((($this->prevCharClass & (self::SPACE | self::NL | self::TEXT_BRACKET)) === self::NIL && $this->prevCharClass != self::NIL) ||
                (($this->nextCharClass & (self::SPACE | self::NL | self::TEXT_BRACKET | self::PUNCTUATION)) || $this->nextCharClass === self::NIL))) ? 'close' : 'open';


        if($type === 'open' && ($this->prevCharClass & (self::SPACE | self::NL | self::TEXT_BRACKET)) === self::NIL && $this->prevCharClass !== self::NIL) {
            return false;
        }

        if($type === 'close' && ($this->nextCharClass & (self::SPACE | self::NL | self::TEXT_BRACKET | self::PUNCTUATION)) === self::NIL && $this->nextCharClass !== self::NIL) {
            return false;
        }

        $this->quotesOpened += ($type === 'open') ? 1 : -1;

        $level = ($type === 'open') ? $this->quotesOpened - 1 : $this->quotesOpened;
        $index = ($type === 'open') ? 0 : 1;

        $quote = $this->quotes[$level][$index];

        $this->moveNextPos();

        return true;
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected function autoReplace($text)
    {
        if (!empty($this->autoReplace)) {
            $text = str_replace(array_keys($this->autoReplace), array_values($this->autoReplace), $text);
        }
        return $text;
    }

    /**
     * Пытается найти и "сделать" текст
     *
     * @return string
     */
    protected function makeText()
    {
        $text = '';

        while($this->curChar !== '<' && $this->curCharClass) {
            $spResult = null;
            $entity = null;
            $quote = null;
            $dash = null;
            $url = null;

            // Преобразование HTML сущностей
            if($this->curChar === '&' && $this->matchHTMLEntity($entity)) {
                $text .= isset($this->entities[$entity]) ? $this->entities[$entity] : $entity;
            }
            // Добавление символов пунктуации
            else if($this->curCharClass & self::PUNCTUATION) {
                $text .= $this->curChar;
                $this->moveNextPos();
            }
            // Преобразование символов тире в длинное тире
            else if($this->typoMode && $this->curChar === '-' && $this->matchDash($dash)) {
                $text .= $dash;
            }
            // Преобразование кавычек
            else if($this->typoMode && ($this->curCharClass & self::TEXT_QUOTE) && $this->matchQuote($quote)) {
                $text .= $quote;
            }
            // Преобразование пробельных символов
            else if($this->curCharClass & self::SPACE) {
                $this->skipSpaces();

                $text .= ' ';
            }
            // Перевод строки
            else if ($this->curCharClass & self::NL) {
                $nlCount = $this->skipNL();
                $nl = $this->isSaveNL ? "\n" : '';
                // Преобразование символов перевода строк в тег <br>
                if($this->isAutoBrMode && !$this->tagsRules($this->curTag, self::TAG_NO_AUTO_BR)) {
                    $nl = $this->br . $nl;
                }
                if ($this->limitNL > 0 && $nlCount > $this->limitNL) {
                    $nlCount = $this->limitNL;
                }
                if ($nl) {
                    $text .= str_repeat($nl, $nlCount);
                } else {
                    $text .= $this->nl;
                }
            }
            // Преобразование текста похожего на ссылку в кликабельную ссылку
            else if($this->isAutoLinkMode && ($this->curCharClass & self::ALPHA) && $this->curTag !== 'a' && $url = $this->matchURL($addr)) {
                $text .= $this->makeTag('a' , ['href' => $url], $addr, false);
            }
            // Вызов callback-функции если строка предварена специальным символом
            else if($this->isSpecialCharMode && ($this->curCharClass & self::SPECIAL_CHAR) && $this->curTag !== 'a' && $this->matchSpecialChar($spResult)) {
                $text .= $spResult;
            }
            // Другие печатные символы
            else if($this->curCharClass & self::PRINTABLE) {
                $text .= isset($this->entities[$this->curChar]) ? $this->entities[$this->curChar] : $this->curChar;
                $this->moveNextPos();
            }
            // Непечатные символы
            else {
                $this->moveNextPos();
            }
        }
        $text = $this->autoReplace($text);

        if ($this->plainTextLimit > 0) {
            // надо контролировать длину "чистого" текста
            $curText = strip_tags($text);
            $curLen = mb_strlen($curText);
            if ($this->plainTextLen + $curLen > $this->plainTextLimit) {
                $text = $this->_cutContent($text, [$this->plainTextLimit - $this->plainTextLen, $this->plainTextBreak - $this->plainTextLen], '', $curLen);
            }
            $this->plainTextLen += $curLen;
            if ($this->plainTextLen >= $this->plainTextLimit) {
                // переносим указатель в конец
                $this->movePos($this->textLen);
            }
        }

        return $text;
    }

    /**
     * Обрезка текста до заданной длины (в тексте могут быть теги)
     *
     * @param string $text
     * @param int|int[] $maxLength - можно указать как число, так и масив [от, до], чтоб по границе слова попробовать обрезать
     * @param string $suffix
     * @param int $cutLength
     *
     * @return string
     */
    protected function _cutContent($text, $maxLength, $suffix = '', &$cutLength = 0)
    {
        if (preg_match_all('/([^\<]+)|(\<\/?([a-zA-Z]+)[^>]*>)|(<)/', $text, $m)) {
            $result = '';
            $tagsStack = [];
            $tagsStackPtr = -1;
            $cutLength = 0;
            $done = false;

            if (is_array($maxLength)) {
                list($needLength, $breakLength) = $maxLength;
            } else {
                $needLength = $breakLength = $maxLength;
            }
            if ($needLength > $breakLength) {
                $needLength = $breakLength;
            }
            foreach ($m[0] as $index => $fragment) {
                if (strpos($fragment, '<') === 0 && substr($fragment, -1) === '>') {
                    $tagName = $m[3][$index];
                    if (substr($fragment, 1, 1) === '/') {
                        // это закрывающий тег
                        if ($tagsStack[0] === $tagName) {
                            unset($tagsStack[$tagsStackPtr--]);
                            $result .= $fragment;
                        }
                    } else {
                        // это открывающий тег
                        if (!$done) {
                            if (!$this->tagsRules($tagName, self::TAG_SHORT)) {
                                $tagsStack[++$tagsStackPtr] = $tagName;
                            }
                            $result .= $fragment;
                        }
                    }
                }
                elseif (!$done) {
                    // это просто текст
                    /** @var int $len */
                    $len = mb_strlen($fragment);
                    if ($cutLength + $len > $needLength) {
                        if ($breakLength > $needLength && preg_match_all('/\S+\s?/siu', $fragment, $m) > 1) {
                            // пробуем обрезать по границе слова
                            $fragment = '';
                            $len = 0;
                            $minLen = $needLength - $cutLength;
                            $maxLen = $breakLength - $cutLength;
                            foreach($m[0] as $word) {
                                $wordLen = mb_strlen($word);
                                if ($len + $wordLen > $maxLen) {
                                    $fragment .= mb_substr($word, 0, $maxLen - $len);
                                    $len += $maxLen - $len;
                                    break;
                                }
                                if ($len + $wordLen >= $minLen) {
                                    $fragment .= $word;
                                    $len += $wordLen;
                                    break;
                                }
                                $fragment .= $word;
                                $len += $wordLen;
                            }
                        } else {
                            $fragment = mb_substr($fragment, 0, $needLength - $cutLength);
                            $len = $needLength;
                        }
                        $fragment .= $suffix;
                    }
                    $cutLength += $len;
                    $result .= $fragment;
                    $done = $cutLength >= $needLength;
                }
                if ($done && empty($tagsStack)) {
                    break;
                }
            }
            return $result;
        }
        return $text;
    }

    /**
     * Определяет текстовые ссылки
     *
     * @param string $addr ссылка
     *
     * @return boolean|string
     */
    protected function matchURL(&$addr = '')
    {
        $url = '';
        if(($this->prevCharClass & (self::SPACE | self::NL | self::TEXT_QUOTE | self::TEXT_BRACKET)) === self::NIL && $this->prevCharClass !== self::NIL) {
            return false;
        }

        $this->saveState();

        if($this->matchStr('http://') && in_array('http', $this->linkProtocolAllowed, true)) {
            //
        }
        else if($this->matchStr('https://') && in_array('https', $this->linkProtocolAllowed, true)) {
            //
        }
        else if($this->matchStr('ftp://') && in_array('ftp', $this->linkProtocolAllowed, true)) {
            //
        }
        else if($this->matchStr('www.')) {
            $url = $this->linkProtocolDefault . '://';
        } else {
            $this->restoreState();
            return false;
        }

        $openBracket = (($this->prevCharClass & self::TEXT_BRACKET) && isset($this->bracketsALL[$this->prevChar])) ? $this->prevChar : null;
        $closeBracket = ($openBracket !== null) ? $this->bracketsALL[$this->prevChar] : null;

        $openedBracket = ($openBracket !== null) ? 1 : 0;

        $buffer = '';
        while($this->curCharClass & self::PRINTABLE) {
            if($this->curChar === '<') {
                break;
            }
            if($this->curCharClass & self::TEXT_QUOTE) {
                break;
            }
            if(($this->curCharClass & self::TEXT_BRACKET) && $openedBracket > 0) {
                if($this->curChar === $closeBracket && $openedBracket === 1) {
                    break;
                }

                if($this->curChar === $openBracket) {
                    ++$openedBracket;
                }
                if($this->curChar === $closeBracket) {
                    --$openedBracket;
                }
            }
            else if($this->curCharClass & self::PUNCTUATION) {
                $this->saveState();
                $punctuation = $this->grabCharClass(self::PUNCTUATION);

                if(($this->curCharClass & self::PRINTABLE) === self::NIL) {
                    $this->restoreState();
                    break;
                }

                $this->removeState();
                $buffer .= $punctuation;

                if($this->curCharClass & (self::TEXT_QUOTE | self::TEXT_BRACKET)) {
                    break;
                }
            }

            $buffer .= $this->curChar;
            $this->moveNextPos();
        }

        if($buffer === '') {
            $this->restoreState();
            return false;
        }
        $this->removeState();

        $url .= $buffer;
        $addr = $buffer;

        return $url;
    }

    /**
     * Определяет строки предваренные спецсимволами
     *
     * @param string $spResult результат работы callback-функции
     *
     * @return boolean
     */
    protected function matchSpecialChar(&$spResult = '')
    {
        if(($this->curCharClass & self::SPECIAL_CHAR) === self::NIL) {
            return false;
        }

        if(!isset($this->specialChars[$this->curChar])) {
            return false;
        }

        if($this->prevCharClass && ($this->prevCharClass & (self::SPACE | self::NL | self::TEXT_BRACKET)) === self::NIL) {
            return false;
        }

        $buffer = '';
        $spChar = $this->curChar;

        $this->saveState();
        $this->moveNextPos();

        if(($this->curCharClass & self::TEXT_BRACKET) && isset($this->bracketsSPC[$this->curChar])) {
            $closeBracket = $this->bracketsSPC[$this->curChar];
            $escape = false;

            $this->moveNextPos();

            while($this->curCharClass && ($this->curCharClass & self::NL) === self::NIL && ($this->curChar !== $closeBracket || $escape === true)) {
                if(($this->curCharClass & self::SPACE) && ($this->prevCharClass & self::SPACE)) {
                    $this->skipSpaces();
                    continue;
                }

                $buffer .= $this->curChar;

                // Возможны экранированные скобки
                $escape = $this->curChar === '\\';

                $this->moveNextPos();
            }

            if($this->curChar !== $closeBracket) {
                $this->restoreState();
                return false;
            }

            $this->moveNextPos();
        }
        else {
            while($this->curCharClass && ($this->curCharClass & (self::SPACE | self::NL | self::TEXT_BRACKET)) === self::NIL) {
                if($this->curCharClass & self::PUNCTUATION) {
                    $this->saveState();

                    $punctuation = $this->grabCharClass(self::PUNCTUATION);

                    if($this->curCharClass & (self::SPACE | self::NL | self::TEXT_BRACKET) || $this->curCharClass == self::NIL)
                    {
                        $this->restoreState();
                        break;
                    }
                    $this->removeState();
                    $buffer .= $punctuation;
                }

                $buffer .= $this->curChar;
                $this->moveNextPos();
            }
        }

        $buffer = trim($buffer);

        if($buffer === '') {
            $this->restoreState();
            return false;
        }

        $spResult = call_user_func($this->specialChars[$spChar], $buffer);

        if(!$spResult) {
            $this->restoreState();
            return false;
        }

        $this->removeState();

        return true;
    }

    /**
     * Возвращает код символа по его строковому представлению
     *
     * @param string $chr символ
     *
     * @return int|boolean
     */
    public static function ord($chr)
    {
        $ord = ord($chr[0]);

        if($ord < 0x80) {
            return $ord;
        }
        if($ord < 0xC2) {
            return false;
        }
        if($ord < 0xE0) {
            return ($ord & 0x1F) << 6 | (ord($chr[1]) & 0x3F);
        }
        if($ord < 0xF0) {
            return ($ord & 0x0F) << 12 | (ord($chr[1]) & 0x3F) << 6 | (ord($chr[2]) & 0x3F);
        }
        if($ord < 0xF5) {
            return ($ord & 0x0F) << 18 | (ord($chr[1]) & 0x3F) << 12 | (ord($chr[2]) & 0x3F) << 6 | (ord($chr[3]) & 0x3F);
        }

        return false;
    }

    /**
     * Возвращает строковое представление символа по его коду
     *
     * @param string $ord код символа
     *
     * @return string|boolean
     */
    public static function chr($ord)
    {
        if($ord < 0x80) {
            return chr($ord);
        }
        if($ord < 0x800) {
            return chr(0xC0 | $ord >> 6) . chr(0x80 | $ord & 0x3F);
        }
        if($ord < 0x10000) {
            return chr(0xE0 | $ord >> 12) . chr(0x80 | $ord >> 6 & 0x3F) . chr(0x80 | $ord & 0x3F);
        }
        if($ord < 0x110000) {
            return chr(0xF0 | $ord >> 18) . chr(0x80 | $ord >> 12 & 0x3F) . chr(0x80 | $ord >> 6 & 0x3F) . chr(0x80 | $ord & 0x3F);
        }

        return false;
    }

    /**
     * Устанавливает сообщение об ошибке (для внутренних нужд)
     *
     * @param string $message сообщение об ошибке
     */
    protected function setError($message)
    {
        $this->errorsList[] = [
            'message'   => $message,
            'position'  => $this->curPos
        ];
    }

    /**
     * Получить сообщения список сообщений об ошибке
     *
     * @return array
     */
    public function getError()
    {
        return $this->errorsList;
    }

    /**
     * Запускает парсер
     *
     * @param string $text текст
     * @param array $maxLen максимальная длина текста
     * @param array $errors сообщения об ошибках
     *
     * @return string
     */
    protected function _parse($text, $maxLen, &$errors = [])
    {
        $this->prevPos = -1;
        $this->prevChar = null;
        $this->prevCharOrd = 0;
        $this->prevCharClass = self::NIL;

        $this->curPos = -1;
        $this->curChar = null;
        $this->curCharOrd = 0;
        $this->curCharClass = self::NIL;

        $this->nextPos = -1;
        $this->nextChar = null;
        $this->nextCharOrd = 0;
        $this->nextCharClass = self::NIL;

        $this->curTag = null;

        $this->statesStack = [];

        $this->quotesOpened = 0;

        $this->plainText = '';
        $this->plainTextLen = 0;
        $this->plainTextLimit = !empty($maxLen[0]) ? $maxLen[0] : 0;
        $this->plainTextBreak = !empty($maxLen[1]) ? $maxLen[1] : 0;

        if ($this->mode === 'text') {
            $text = htmlspecialchars($text);
        }
        $text = str_replace("\r", '', $text);

        $this->textBuf = $this->strToArray($text);
        $this->textLen = count($this->textBuf);

        $this->errorsList = [];

        $this->movePos(0);

        $content = $this->makeContent();
        $content = ($this->nl !== "\n") ? str_replace("\n", $this->nl, $content) : $content;
        $content = trim($content);

        $errors = $this->errorsList;

        return $content;
    }

    /**
     * Парсинг текста в соответствии с текущими настройками
     *
     * @param string $text текст
     * @param array $errors сообщения об ошибках
     *
     * @return string
     */
    public function parse($text, &$errors = [])
    {
        return $this->_parse($text, [0, 0], $errors);
    }

    /**
     * Обрезка текста в соответствии с текущими настройками
     * Если лимит задан массивом, то делается попытка обрезать текст по границе слова так,
     * чтоб длина результирующего текста была между двумя этими значениями
     *
     * @param string $text
     * @param int|array $maxLen - либо число, лимитирующее размер выходного текста, либо массив - минимальное и максимальное значение
     * @param array $errors
     *
     * @return string
     */
    public function cut($text, $maxLen, &$errors = [])
    {
        if (!is_array($maxLen)) {
            $maxLen = [$maxLen, $maxLen];
        }
        return $this->_parse($text, $maxLen, $errors);
    }

    /**
     * Удаляет из текста все теги (но не их содержимое) и выполняет типографирование в соответствии с текущими настройками
     *
     * @param string $text
     * @param array $errors
     *
     * @return string
     */
    public function plain($text, &$errors = [])
    {
        $tagRules = $this->tagsRules;
        $this->tagsRules = [];
        $text = $this->parse($text, $errors);
        $this->tagsRules = $tagRules;

        return $text;
    }

}

// EOF
