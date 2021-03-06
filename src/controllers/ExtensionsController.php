<?php

namespace DevGroup\ExtensionsManager\controllers;

use DevGroup\AdminUtils\controllers\BaseController;
use DevGroup\DeferredTasks\actions\ReportQueueItem;
use DevGroup\DeferredTasks\helpers\DeferredHelper;
use DevGroup\DeferredTasks\helpers\ReportingChain;
use DevGroup\ExtensionsManager\actions\ConfigurationIndex;
use DevGroup\ExtensionsManager\ExtensionsManager;
use DevGroup\ExtensionsManager\helpers\ExtensionDataHelper;
use DevGroup\ExtensionsManager\models\Extension;
use Packagist\Api\Client;
use Yii;
use yii\base\InvalidParamException;
use yii\data\ArrayDataProvider;
use yii\helpers\Json;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class ExtensionsController extends BaseController
{
    /** @var  Client packagist.org API client instance */
    private static $packagist;

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'config' => [
                'class' => ConfigurationIndex::className(),
            ],
            'deferred-report-queue-item' => [
                'class' => ReportQueueItem::className(),
            ],
        ];
    }

    public function behaviors()
    {
        return [
            'accessControl' => [
                'class' => '\yii\filters\AccessControl',
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'details', 'deferred-report-queue-item', 'search', 'run-task'],
                        'roles' => ['extensions-manager-view-extensions'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['config'],
                        'roles' => ['extensions-manager-configure-extension'],
                    ],
                    [
                        'allow' => false,
                        'roles' => ['*'],
                    ]
                ],
            ],
        ];
    }

    /**
     *Shows installed extensions
     */
    public function actionIndex()
    {
        $extensions = ExtensionsManager::module()->getExtensions();
        return $this->render(
            'index',
            [
                'dataProvider' => new ArrayDataProvider([
                    'allModels' => $extensions,
                    'sort' => [
                        'attributes' => ['composer_name', 'composer_type', 'is_active'],
                    ],
                    'pagination' => [
                        'defaultPageSize' => 10,
                        'pageSize' => ExtensionsManager::module()->extensionsPerPage,
                    ],
                ]),
            ]
        );
    }

    /**
     * Searching extensions packages using packagist API.
     * Pckagist API gives us ability to filter packages by type and vendor.
     * Supported types are: Extension::getTypes();
     * Vendor filter extracts from query string. If query string contains / or \ all string before it will be
     * recognized as vendor and added into API query.
     *
     * @param string $sort
     * @param string $type
     * @param string $query
     * @return \DevGroup\AdminUtils\response\AjaxResponse|string
     */
    public function actionSearch($sort = '', $type = Extension::TYPE_DOTPLANT, $query = '')
    {
        $packagist = self::getPackagist();
        $type = empty($type) ? Extension::TYPE_DOTPLANT : $type;
        $filters = ['type' => $type];
        if (1 === preg_match('{([\\\\/])}', $query, $m)) {
            $queryArray = explode($m[0], $query);
            $filters['vendor'] = array_shift($queryArray);
        }
        $packages = $packagist->search($query, $filters);
        return $this->renderResponse(
            'search',
            [
                'dataProvider' => new ArrayDataProvider([
                    'allModels' => $packages,
                    'pagination' => [
                        'defaultPageSize' => 10,
                        'pageSize' => ExtensionsManager::module()->extensionsPerPage,
                    ],
                ]),
                'type' => $type,
            ]
        );
    }

    /**
     * Process API requests to github and packagist
     *
     * @param $url
     * @param array $headers
     * @return mixed
     */
    private static function doRequest($url, $headers = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        if (0 !== $errno = curl_errno($ch)) {
            $errorMessage = curl_strerror($errno);
            Yii::$app->session->setFlash("cURL error ({$errno}):\n {$errorMessage}");
        }
        curl_close($ch);
        return $response;
    }

    /**
     * @return Client
     */
    private static function getPackagist()
    {
        if (true === empty(self::$packagist) || false === self::$packagist instanceof Client) {
            $packagist = new Client();
            $packagist->setPackagistUrl(ExtensionsManager::module()->packagistUrl);
            self::$packagist = $packagist;
        }
        return self::$packagist;
    }

    /**
     * @param $repo
     * @return bool
     */
    public static function isGit($repo)
    {
        return false !== strpos($repo, 'github');
    }

    /**
     * Method collects an extended package data from packagist.org and github.com
     * other services are not supported yet.
     *
     * @return \DevGroup\AdminUtils\response\AjaxResponse|string
     * @throws NotFoundHttpException
     */
    public function actionDetails()
    {
        if (false === Yii::$app->request->isAjax) {
            throw new NotFoundHttpException("Page not found");
        }
        $module = ExtensionsManager::module();
        $packageName = Yii::$app->request->post('packageName');
        $packagist = self::getPackagist();
        $package = $packagist->get($packageName);
        $repository = $package->getRepository();
        $packagistVersions = $package->getVersions();
        $readme = '';
        $versionsData = $dependencies = [];
        if (true === self::isGit($repository)) {
            $repository = preg_replace(['%^.*github.com\/%', '%\.git$%'], '', $repository);
            $gitAccessToken = $module->githubAccessToken;
            $gitApiUrl = rtrim($module->githubApiUrl, '/');
            $applicationName = $module->applicationName;
            $headers = [
                'User-Agent: ' . $applicationName,
            ];
            if (false === empty($gitAccessToken)) {
                $headers[] = 'Authorization: token ' . $gitAccessToken;
            }
            $gitReadmeUrl = $gitApiUrl . '/repos/' . $repository . '/readme';
            $gitReleasesUrl = $gitApiUrl . '/repos/' . $repository . '/releases';
            $readmeData = self::doRequest($gitReadmeUrl, $headers);
            $readme = ExtensionDataHelper::humanizeReadme($readmeData);
            $versionsData = Json::decode(self::doRequest($gitReleasesUrl, $headers));
            if (true === empty($versionsData)) {
                $gitTagsUrl = $gitApiUrl . '/repos/' . $repository . '/tags';
                $versionsData = Json::decode(self::doRequest($gitTagsUrl, $headers));
            }
        }
        //ExtensionDataHelper::getVersions() must be invoked before other methods who fetches versioned data
        $versions = ExtensionDataHelper::getVersions($packagistVersions, array_shift($versionsData));
        $jsonUrl = rtrim($module->packagistUrl, '/') . '/packages/' . trim($packageName, '/ ') . '.json';
        $packageJson = self::doRequest($jsonUrl);
        $packageData = Json::decode($packageJson);
        $type = ExtensionDataHelper::getType($packageData);

        return $this->renderResponse(
            '_ext-details',
            [
                'readme' => $readme,
                'versions' => $versions,
                'description' => ExtensionDataHelper::getLocalizedVersionedDataField(
                    $packageData,
                    Extension::TYPE_YII,
                    'description'
                ),
                'name' => ExtensionDataHelper::getLocalizedVersionedDataField(
                    $packageData,
                    Extension::TYPE_YII,
                    'name'
                ),
                'dependencies' => [
                    'require' => ExtensionDataHelper::getOtherPackageVersionedData($packageData, 'require'),
                    'require-dev' => ExtensionDataHelper::getOtherPackageVersionedData($packageData, 'require-dev'),
                ],
                'authors' => ExtensionDataHelper::getOtherPackageVersionedData($packageData, 'authors'),
                'license' => ExtensionDataHelper::getOtherPackageVersionedData($packageData, 'license'),
                'packageName' => $packageName,
                'installed' => array_key_exists($packageName, $module->getExtensions()),
                'type' => $type,
            ]
        );
    }


    /**
     * Common method to access from web via ajax requests. Builds a ReportingChain and immediately fires it.
     *
     * @return array
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     * @throws InvalidParamException
     */
    public function actionRunTask()
    {
        if (false === Yii::$app->request->isAjax) {
            throw new NotFoundHttpException('Page not found');
        }
        $module = ExtensionsManager::module();
        $packageName = Yii::$app->request->post('packageName');
        $extension = $module->getExtensions($packageName);
        $taskType = Yii::$app->request->post('taskType');
        if (true === empty($extension) && $taskType != ExtensionsManager::INSTALL_DEFERRED_TASK) {
            return self::runTask(
                [
                    realpath(Yii::getAlias('@app') . '/yii'),
                    'extension/dummy',
                    'Undefined extension: ' . $packageName,
                ],
                ExtensionsManager::EXTENSION_DUMMY_DEFERRED_GROUP
            );
        }
        if ($module->extensionIsCore($packageName)
            && !Yii::$app->user->can('extensions-manager-access-to-core-extension')
        ) {
            throw new ForbiddenHttpException;
        }
        $chain = new ReportingChain();
        switch ($taskType) {
            case ExtensionsManager::INSTALL_DEFERRED_TASK :
                if ($module->extensionIsCore($packageName)
                    || !Yii::$app->user->can('extensions-manager-install-extension')
                ) {
                    throw new ForbiddenHttpException;
                }
                return self::runTask(
                    [
                        $module->composerPath,
                        'require',
                        $packageName,
                        '--no-interaction',
                        '--no-ansi',
                        "--working-dir={$module->getLocalExtensionsPath()}",
                        '--prefer-dist',
                        '-o',
                        $module->verbose == 1 ? '-vvv' : '',
                    ],
                    ExtensionsManager::COMPOSER_INSTALL_DEFERRED_GROUP
                );
            case ExtensionsManager::UNINSTALL_DEFERRED_TASK :
                if ($module->extensionIsCore($packageName)
                    || !Yii::$app->user->can('extensions-manager-uninstall-extension')
                ) {
                    throw new ForbiddenHttpException;
                }
                self::uninstall($extension, $chain);
                break;
            case ExtensionsManager::ACTIVATE_DEFERRED_TASK :
                if (!Yii::$app->user->can('extensions-manager-activate-extension')) {
                    throw new ForbiddenHttpException;
                }
                self::activate($extension, $chain);
                break;
            case ExtensionsManager::DEACTIVATE_DEFERRED_TASK :
                if (!Yii::$app->user->can('extensions-manager-deactivate-extension')) {
                    throw new ForbiddenHttpException;
                }
                self::deactivate($extension, $chain);
                break;
            default:
                return self::runTask(
                    [
                        realpath(Yii::getAlias('@app') . '/yii'),
                        'extension/dummy',
                        'Unrecognized task!',
                    ],
                    ExtensionsManager::EXTENSION_DUMMY_DEFERRED_GROUP
                );
        }
        if (null !== $firstTaskId = $chain->registerChain()) {
            DeferredHelper::runImmediateTask($firstTaskId);
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'queueItemId' => $firstTaskId,
            ];
        } else {
            throw new ServerErrorHttpException("Unable to start chain");
        }
    }

    /**
     * Adds deactivation task into ReportingChain if Extension is active
     *
     * @param array $extension
     * @param ReportingChain $chain
     */
    private static function deactivate($extension, ReportingChain $chain)
    {
        if ($extension['is_active'] == 1) {
            ExtensionDataHelper::prepareMigrationTask(
                $extension,
                $chain,
                ExtensionsManager::MIGRATE_TYPE_DOWN,
                ExtensionsManager::EXTENSION_DEACTIVATE_DEFERRED_GROUP
            );
            $deactivationTask = ExtensionDataHelper::buildTask(
                [
                    realpath(Yii::getAlias('@app') . '/yii'),
                    'extension/deactivate',
                    $extension['composer_name'],
                ],
                ExtensionsManager::EXTENSION_DEACTIVATE_DEFERRED_GROUP
            );
            $chain->addTask($deactivationTask);
        } else {
            $dummyTask = ExtensionDataHelper::buildTask(
                [
                    realpath(Yii::getAlias('@app') . '/yii'),
                    'extension/dummy',
                    'Extension already deactivated!',
                ],
                ExtensionsManager::EXTENSION_DUMMY_DEFERRED_GROUP
            );
            $chain->addTask($dummyTask);
        }
    }

    /**
     * Adds activation task into ReportingChain if Extension is not active
     *
     * @param array $extension
     * @param ReportingChain $chain
     */
    private static function activate($extension, ReportingChain $chain)
    {
        if ($extension['is_active'] == 0) {
            ExtensionDataHelper::prepareMigrationTask(
                $extension,
                $chain,
                ExtensionsManager::MIGRATE_TYPE_UP,
                ExtensionsManager::EXTENSION_ACTIVATE_DEFERRED_GROUP
            );
            $activationTask = ExtensionDataHelper::buildTask(
                [
                    realpath(Yii::getAlias('@app') . '/yii'),
                    'extension/activate',
                    $extension['composer_name'],
                ],
                ExtensionsManager::EXTENSION_ACTIVATE_DEFERRED_GROUP
            );
            $chain->addTask($activationTask);
        } else {
            $dummyTask = ExtensionDataHelper::buildTask(
                [
                    realpath(Yii::getAlias('@app') . '/yii'),
                    'extension/dummy',
                    'Extension already activated!',
                ],
                ExtensionsManager::EXTENSION_DUMMY_DEFERRED_GROUP
            );
            $chain->addTask($dummyTask);
        }
    }

    /**
     * Adds uninstall task into ReportingChain
     *
     * @param $extension
     * @param ReportingChain $chain
     */
    private static function uninstall($extension, ReportingChain $chain)
    {
        $module = ExtensionsManager::module();
        if (true === $module->extensionIsCore($extension['composer_name'])) {
            $dummyTask = ExtensionDataHelper::buildTask(
                [
                    realpath(Yii::getAlias('@app') . '/yii'),
                    'extension/dummy',
                    '--no-interaction',
                    '-o',
                    'You are unable to uninstall core extensions!',
                ],
                ExtensionsManager::EXTENSION_DUMMY_DEFERRED_GROUP
            );
            $chain->addTask($dummyTask);
        } else {
            self::deactivate($extension, $chain);
            $uninstallTask = ExtensionDataHelper::buildTask(
                [
                    $module->composerPath,
                    'remove',
                    $extension['composer_name'],
                    '--no-ansi',
                    '--no-interaction',
                    "--working-dir={$module->getLocalExtensionsPath()}",
                    $module->verbose == 1 ? '-vvv' : '',
                ],
                ExtensionsManager::COMPOSER_UNINSTALL_DEFERRED_GROUP
            );
            $chain->addTask($uninstallTask);
        }
    }





    /**
     * Runs separated ReportingTask
     *
     * @param $command
     * @param $groupName
     * @return array
     * @throws ServerErrorHttpException
     */
    private static function runTask($command, $groupName)
    {
        $task = ExtensionDataHelper::buildTask($command, $groupName);
        if ($task->registerTask()) {
            DeferredHelper::runImmediateTask($task->model()->id);
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'queueItemId' => $task->model()->id,
            ];
        } else {
            throw new ServerErrorHttpException("Unable to start task");
        }
    }
}
