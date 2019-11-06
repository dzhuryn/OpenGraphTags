<?php


class openGraphTags
{
    /** @var $params array */
    private $params;
    /** @var $modx DocumentParser */
    private $modx;

    private $pageTemplate = 0;
    public $permission = true;
    private $metaTags = [];



    private $ogFields = [];

    /**
     * openGraphTags constructor.
     * @param $modx DocumentParser
     * @param $params array
     */
    public function __construct($modx, $params)
    {
        $this->modx = $modx;
        $this->params = $params;
        $this->pageTemplate = $modx->documentObject['template'];
        $this->metaTags = isset($params['metaTags'])?explode('',str_replace(" ", "", $this->params['metaTags'])):['site_name', 'locale', 'type', 'title', 'description', 'image', 'url'];

        $this->setFields();
    }

    private function setFields()
    {
        $this->getSiteName();
        $this->getLocale();
        $this->getType();
        $this->getTitle();
        $this->getDescription();
        $this->getImage();
        $this->getUrl();
        $this->checkPermission();

    }

    private function getSiteName()
    {
        $name = $this->modx->getConfig('site_name');
        if (!empty($this->params['siteName'])) {
            $name = $this->params['siteName'];
        }
        $this->ogFields['site_name'] = $name;
    }

    private function getLocale()
    {
        $locale = isset($this->params['locale']) ? $this->params['locale'] : "ru_RU";
        $this->ogFields['locale'] = $locale;
    }

    private function getType()
    {
        $type = isset($this->params['defaultType']) ? $this->params['defaultType'] : 'website';
        $templateTypes = [];
        foreach ($this->params as $paramName => $paramValue) {
            if (strpos($paramName, 'type') === 0) {
                $typeName = strtolower(str_replace('type', '', $paramName));
                $templates = explode(',', $paramValue);
                foreach ($templates as $template) {
                    $templateTypes[$template] = $typeName;
                }
            }
        }
        if (isset($templateTypes[$this->pageTemplate])) {
            $type = $templateTypes[$this->pageTemplate];
        }
        if(!empty($this->params['type'])){
            $type = $this->params['type'];
        }
        $this->ogFields['type'] = $type;
    }

    private function getTitle()
    {

        if (!empty($this->params['title'])) {
            $title = $this->params['title'];
        } else if (!empty($this->modx->documentObject['og_title'][1])) {
            $title = $this->modx->documentObject['og_title'][1];
        } else {
            $title = $this->modx->documentObject['pagetitle'];
        }
        $this->ogFields['title'] = $title;
        $this->ogFields['e.title'] = htmlentities($title, ENT_COMPAT, 'UTF-8', false);

        return $title;
    }

    private function getDescription()
    {
        $summaryContent = isset($this->params['summaryContent']) ? $this->params['summaryContent'] : '1';
        if (!empty($this->params['description'])) {
            $description = $this->params['description'];
        } else if (!empty($this->modx->documentObject['og_description'][1])) {
            $description = $this->modx->documentObject['og_description'][1];
        } else if (!empty($this->modx->documentObject['introtext'])) {
            $description = $this->modx->documentObject['introtext'];
        } else {
            $description = $this->modx->documentObject['content'];
            if ($summaryContent == '1') {
                $description = $this->modx->runSnippet('summary', array('text' => $this->modx->documentObject['content'], 'len' => '50'));
            }

        }
        $this->ogFields['description'] = $description;
        $this->ogFields['e.description'] = htmlentities($description, ENT_COMPAT, 'UTF-8', false);
    }

    private function getImage()
    {

        $defaultImage = isset($this->params['defaultImage']) ? $this->params['defaultImage'] : '';
        $imageStorage = !empty($this->params['imageStorage']) ? explode(',', $this->params['imageStorage']) : [];
        $imageSrc = $this->checkImage($this->params['image'])?$this->params['image']:'';

        $thumbSnippet = isset($this->params['thumbSnippet']) ? $this->params['thumbSnippet'] : 'phpthumb';
        $thumbOptions = isset($this->params['thumbOptions']) ? $this->params['thumbOptions'] : '';


        //если у нас не указана картинка по умолчанию, и не указано где искать картинку, получаем все тв поля из типом image
        if (empty($imageStorage) && empty($defaultImage)) {
            $tvs = $this->modx->db->makeArray($this->modx->db->select('name', $this->modx->getFullTableName('site_tmplvars'), 'type = "image"'));
            $imageStorage = array_merge(['SimpleGalleryImage'], array_column($tvs, 'name'));
        }

        if(empty($this->params['image']) && $this->checkImage($this->modx->documentObject['og_image'][1])){
            $imageSrc = $this->modx->documentObject['og_image'][1];
        }


        foreach ($imageStorage as $storageName) {
            if (!empty($imageSrc)) {
                break;
            }

            $tvData = [];
            if ($storageName !== 'SimpleGalleryImage') {
                $tvData = $this->modx->getTemplateVar($storageName, '*');
            }

            // ищем в галерее
            if ($storageName === 'SimpleGalleryImage') {
                $image = $this->modx->runSnippet('sgLister', [
                    'display' => 1,
                    'api' => 1,
                ]);

                $image = json_decode($image, true);
                if (!empty($image) && is_array($image)) {
                    $firstImage = array_shift($image);
                    if ($this->checkImage($firstImage['sg_image'])) {
                        $imageSrc = $firstImage['sg_image'];
                    }
                }
            } //ищем в обычном тв поле
            else if ($tvData['type'] === 'image' && !empty($tvData['value'])) {
                if ($this->checkImage($tvData['value'])) {
                    $imageSrc = $tvData['value'];
                }
            } //ищем в multitv
            else if ($tvData['type'] === 'custom_tv:multitv' && !empty($tvData['value'])) {
                $imageList = json_decode($tvData['value'], true)["fieldValue"];
                $imageFieldKey = isset($this->params[$storageName . 'FieldKey']) ? $this->params[$storageName . 'FieldKey'] : 'image';
                if ($this->checkImage($imageList[0][$imageFieldKey])) {
                    $imageSrc = $imageList[0][$imageFieldKey];
                }
            }
        }


        if (empty($imageSrc) && $this->checkImage($defaultImage)) {
            $imageSrc = $defaultImage;
        }


        $imageSrc = $this->normalizeImageSrc($imageSrc);
        if(empty($imageSrc)){
            return false;
        }

        $imageFull = urldecode(MODX_BASE_PATH . $imageSrc);

        //если указали снииппет для обрезки но не указали  условия ищем сами
        if (empty($thumbOptions) && !empty($thumbSnippet) && file_exists($imageFull)) {
            $info = getimagesize($imageFull);
            $width = $info[0];
            $height = $info[1];

            if ($width > $height) {
                $thumbOptions = 'h=400,zc=C';
            } else {
                $thumbOptions = 'w=400,zc=C';
            }

        }

        if (!empty($thumbSnippet) && !empty($thumbOptions)) {
            $imageSrc = $this->modx->runSnippet($thumbSnippet, array("input" => $imageSrc, "options" => $thumbOptions));
        }

        $imageSrc = $this->normalizeImageSrc($imageSrc);

        $imageSrcFull = $this->modx->getConfig('site_url') . $imageSrc;
        $this->ogFields['image'] = $imageSrcFull;
    }


    /**
     * Убираем / в начале строки
     *
     * @param $src string
     * @return string
     */
    private function normalizeImageSrc($src)
    {
        if (substr($src, 0, 1) == '/') {
            $src = substr($src, 1);
        }
        return $src;
    }

    /**
     *  Отсеиваем картинки у которых ширина или высота меньше 200 и которые отсутствуют на серваке
     *
     * @param $src
     * @return bool
     */
    private function checkImage($src)
    {
        if(empty($src)){
            return  false;
        }
        $src = $this->normalizeImageSrc($src);

        $imageFull = urldecode(MODX_BASE_PATH . $src);
        if (!file_exists($imageFull)) {
            return false;
        }

        $info = getimagesize($imageFull);
        if ($info[0] < 200 || $info[1] < 200) {
            return false;
        }
        return true;
    }

    private function getUrl()
    {
        $url = $this->modx->makeUrl($this->modx->documentIdentifier,'','','full');
        if(!empty($this->params['url'])){
            $url = $this->params['url'];
        }

        $this->ogFields['url'] = $url;
    }

    /**
     * Проверяем можна ли в текущем шаблоне выводить og меги
     */
    private function checkPermission()
    {

        //если стоит тв не показовать og теги на текущей странице
        if($this->modx->documentObject['og_off'][1] == '1'){
            $this->permission = false;
        }
        $tplList = isset($this->params['tplList']) ? str_replace(" ", "", $this->params['tplList']) : 'all';
        $_tplList = explode(",", $tplList);

        //если указан набор шаблонов для вывода и текущей шаблон туда не входит
        if($tplList !== 'all' && !in_array($this->pageTemplate,$_tplList)){
            $this->permission = false;
        }

        $this->ogFields['permission'] = $this->permission;

    }

    public function render()
    {
        $tpl = isset($this->params['tpl'])?$this->params['tpl']: '@CODE:'."\t\n".'<meta property="og:[+type+]" content="[+value+]">';

        $output = '';
        foreach ($this->metaTags as $tagName) {
            $value = $this->ogFields['e.'.$tagName]?$this->ogFields['e.'.$tagName]:$this->ogFields[$tagName];
            if(empty($value)){
                continue;
            }

            $render  = DLTemplate::getInstance($this->modx);

            $output .= $render->parseChunk($tpl,[
                'type'=>$tagName,
                'value'=>$value,
            ]);



        }
        return $output;
    }

    public function getFields()
    {
        return $this->ogFields;
    }


}