<?php

namespace barrelstrength\sproutformsusstates\integrations\sproutforms\fields;

use barrelstrength\sproutforms\elements\Entry;
use Craft;
use craft\helpers\Template as TemplateHelper;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use CommerceGuys\Addressing\Subdivision\Subdivision;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;

use barrelstrength\sproutforms\base\FormField;
use Twig_Error_Loader;
use Twig_Markup;
use yii\base\Exception;
use craft\helpers\Db;
use craft\helpers\Localization;

/**
 * Class States
 *
 * @package Craft
 *
 * @property mixed $settingsHtml
 */
class States extends FormField implements PreviewableFieldInterface
{
    /**
     * @var string
     */
    public $cssClasses;

    /**
     * @var int|null
     */
    public $start = 1;
    public $end = 100;
    public $range = 9;

    public $secondInputTitle = 'Choose Number';


    public function init()
    {
        // Normalize $start
        if ($this->start !== null && $this->start !== '0' && empty($this->start)) {
            $this->start = null;
        }

        // Normalize $end
        if ($this->end !== null && $this->end !== '0' && empty($this->end)) {
            $this->end = null;
        }

        // Normalize $range
        if ($this->range !== null && $this->range !== '0' && empty($this->range)) {
            $this->range = null;
        }

        // Normalize $secondInputTitle
        if ($this->secondInputTitle !== null && $this->secondInputTitle === null) {
            $this->secondInputTitle = null;
        }

        parent::init();
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    // public function getContentColumnType(): string
    // {
    //     return Db::getNumericalColumnType($this->start, $this->end, $this->range);
    // }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        // Is this a post request?
        $request = Craft::$app->getRequest();

        if (!$request->getIsConsoleRequest() && $request->getIsPost() && $value !== '') {
            // Normalize the number and make it look like this is what was posted
            $value = Localization::normalizeNumber($value);
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-forms-us-states', 'Rider Number');
    }

    /**
     * @return string
     */
    public function getSvgIconPath(): string
    {
        return '@sproutformsusstatesicons/rider-number.svg';
    }

    /**
     * @inheritdoc
     *
     * @throws Twig_Error_Loader
     * @throws Exception
     */
    public function getSettingsHtml()
    {
        $rendered = Craft::$app->getView()->renderTemplate(
            'sprout-forms-us-states/_integrations/sproutforms/formtemplates/fields/number/settings',
            [
                'field' => $this,
            ]
        );

        return $rendered;
    }

    /**
     * @inheritdoc
     *
     * @throws Twig_Error_Loader
     * @throws Exception
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        $data = json_decode($value);
        $data = explode('-', $data[0]);
        $numbers = [];
        for ($i=$data[0]; $i < $data[1]; $i++) { 
            $numbers[$i] = $i;
        }
        return Craft::$app->getView()->renderTemplate('sprout-forms-us-states/_integrations/sproutforms/formtemplates/fields/number/cpinput',
            [
                'name' => $this->handle,
                'value' => $value,
                'field' => $this,
                'options' => $this->getOptions($this->start,$this->end,$this->range),
                'options2' => $numbers,
            ]
        );
    }

    /**
     * @inheritdoc
     *
     * @throws Twig_Error_Loader
     * @throws Exception
     */
    public function getExampleInputHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('sprout-forms-us-states/_integrations/sproutforms/formtemplates/fields/number/example',
            [
                'field' => $this,
                'options' => $this->getOptions($this->start,$this->end,$this->range),
            ]
        );
    }

    /**
     * @inheritdoc
     *
     * @throws Twig_Error_Loader
     * @throws Exception
     */
    public function getFrontEndInputHtml($value, Entry $entry, array $renderingOptions = null): Twig_Markup
    {
        $rendered = Craft::$app->getView()->renderTemplate(
            'number/input',
            [
                'name' => $this->handle,
                'value' => $value,
                'field' => $this,
                'entry' => $entry,
                'options' => $this->getOptions($this->start,$this->end,$this->range),
                'renderingOptions' => $renderingOptions,
                'secondInputTitle' => $this->secondInputTitle
            ]
        );

        return TemplateHelper::raw($rendered);
    }

    // public function getElementValidationRules(): array
    // {
    //     return [
    //         ['number', 'start' => $this->start, 'end' => $this->end, 'range' => $this->range],
    //     ];
    // }

    /**
     * @inheritdoc
     */
    public function getTemplatesPath(): string
    {
        return Craft::getAlias('@barrelstrength/sproutformsusstates/templates/_integrations/sproutforms/formtemplates/fields/');
    }

    /**
     * @return array
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['start', 'end', 'range'], 'number'];
        $rules[] = [
            ['end'],
            'compare',
            'compareAttribute' => 'start',
            'operator' => '>='
        ];

        return $rules;
    }


    /**
     * Return Number Range as options for select field
     *
     * @return array
     */
    private function getOptions($start,$end,$range): array
    {
        $options = [];
        for ($start; $start < $end; $start ++) {
            $to = $start + $range;
            $options[$start.'-'.$to] = $start.'-'.$to;
            $start = $to;
        }

        return $options;
    }
}
