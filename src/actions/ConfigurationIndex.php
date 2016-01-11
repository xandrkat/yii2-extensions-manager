<?php

namespace DevGroup\ExtensionsManager\actions;

use DevGroup\AdminUtils\actions\TabbedFormCombinedAction;
use DevGroup\ExtensionsManager\ExtensionsManager;
use DevGroup\ExtensionsManager\helpers\ExtensionsHelper;
use DevGroup\ExtensionsManager\models\BaseConfigurationModel;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class ConfigurationIndex extends TabbedFormCombinedAction
{
    /** @var BaseConfigurationModel */
    public $model = null;

    /** @var array All configurables array */
    public $configurables = [];

    /** @var array Current selected configurable */
    public $currentConfigurable = [];

    /** @var string Name of configuration model */
    public $currentConfigurationModel = null;

    /** @var string Configuration view */
    public $currentConfigurationView = '';

    /** @var int Current section index in $configurables array */
    public $sectionIndex = 0;

    /** @var bool If current selected section is valid */
    public $isValidSection = false;

    public function beforeActionRun()
    {
        parent::beforeActionRun();

        $this->configurables = ExtensionsHelper::getConfigurables();

        $this->sectionIndex = Yii::$app->request->get('sectionIndex', 0);
        if (isset($this->configurables[$this->sectionIndex]) === false) {
            $this->sectionIndex = 0;
        }
        $this->sectionIndex = intval($this->sectionIndex);

        $this->currentConfigurable = $this->configurables[$this->sectionIndex];

        $this->currentConfigurationModel = ArrayHelper::getValue($this->currentConfigurable, 'configurationModel');
        $this->currentConfigurationView = ArrayHelper::getValue($this->currentConfigurable, 'configurationView');
        if ($this->currentConfigurationView !== null && $this->currentConfigurationModel !== null) {
            $this->isValidSection = true;
            $this->currentConfigurationView = '@vendor/'
                . $this->currentConfigurable['package']
                . '/' . $this->currentConfigurationView;
            $this->model = new $this->currentConfigurationModel;
            /** @var ExtensionsManager $module */
            $module = Yii::$app->getModule('extensions-manager');
            $configurablesStatePath = $module->configurationUpdater->configurablesStatePath;
            $this->model->loadState($configurablesStatePath);
        }
    }

    public function defineParts()
    {
        return [
            'links' => [
                'function' => 'sectionLinks',
                'title' => Yii::t('extensions-manager', 'Configuration'),
                'icon' => 'fa fa-list-alt',
                'type' => TabbedFormCombinedAction::TYPE_TABS_LINKS,
            ],
            'saveData' => [
                'function' => 'saveData',
            ],
            'renderSectionForm' => [
                'function' => 'renderSectionForm',
                'title' => $this->currentConfigurable['sectionNameTranslated'],
                'icon' => 'fa fa-cogs',
                'footer' => $this->getFooter(),
            ],
        ];
    }

    public function sectionLinks()
    {
        $navItems = [];

        foreach ($this->configurables as $index => $item) {
            $navItem = [
                'label' => $item['sectionNameTranslated'],
                'url' => [$this->id, 'sectionIndex' => $index],
            ];
            if ($index === $this->sectionIndex) {
                $navItem['active'] = true;
            }
            $navItems[] = $navItem;

        }
        return $navItems;
    }

    public function saveData()
    {
        if (isset($this->model) === false) {
            return '';
        }

        if ($this->model->load(Yii::$app->request->post()) && $this->model->validate()) {
            /** @var ExtensionsManager $extensionsManager */
            $extensionsManager = Yii::$app->getModule('extensions-manager');
            if ($extensionsManager->configurationUpdater->updateConfiguration(true)) {
                return $this->controller->redirect([$this->id, 'sectionIndex' => $this->sectionIndex]);
            }
        }
        return '';
    }

    public function renderSectionForm()
    {
        return $this->render(
            $this->currentConfigurationView,
            [
                'model' => $this->model,
                'configurable' => $this->currentConfigurable,
                'form' => $this->form,
            ]
        );
    }

    public function getFooter()
    {
        return Html::submitButton(
            '<i class="fa fa-floppy-o"></i>&nbsp;' .
            (
            Yii::t('app', 'Save')
            ),
            [
                'class' => 'btn btn-primary pull-right',
            ]
        );
    }

    public function breadcrumbs()
    {
        return [];
    }

    public function title()
    {
        return Yii::t('extensions-manager', 'Configuration');
    }
}