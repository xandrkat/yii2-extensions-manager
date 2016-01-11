<?php
/**
 * @var string $readme
 * @var array $versions
 * @var string $description
 * @var string $name
 * @var array $dependencies
 * @var array $authors
 * @var array $license
 * @var string $packageName
 */
?>
<tr class="extension-info-tr">
    <td colspan="4">
        <div class="box">
            <div class="row">
                <div class="col-sm-12">
                    <button class="btn btn-success pull-right" data-action="ext-install"
                            data-reponame="<?= $packageName ?>">
                        <?= Yii::t('extensions-manager', 'Install') ?>
                    </button>
                </div>
            </div>
            <div class="row box box-solid">
                <div class="col-md-6">
                    <div>
                        <div class="box-header">
                            <h3 class="box-title"><?= Yii::t('extensions-manager', 'Authors') ?></h3>
                        </div>
                        <div class="box-body">
                            <?php foreach ($authors as $author) : ?>
                                <dl class="dl-horizontal">
                                    <dt><?= Yii::t('extensions-manager', 'Author name') ?></dt>
                                    <dd><?= $author["name"] ?></dd>
                                </dl>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <div class="box-header">
                            <h3 class="box-title"><?= Yii::t('extensions-manager', 'License') ?></h3>
                        </div>
                        <div class="box-body">
                            <?php foreach ($license as $l) : ?>
                                <dl class="dl-horizontal">
                                    <dt><?= Yii::t('extensions-manager', 'License type') ?></dt>
                                    <dd><?= $l ?></dd>
                                </dl>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div>
                        <div class="box-header">
                            <h3 class="box-title"><?= Yii::t('extensions-manager', 'Versions') ?></h3>
                        </div>
                        <div class="box-body">
                            <?php
                            $current = $versions['current'];
                            unset($versions['current']);
                            foreach ($versions as $name => $time) :?>
                                <dl class="dl-horizontal">
                                    <dt>
                                        <?= $name ?>
                                        <?= $name == $current ?
                                            '(' . Yii::t('extensions-manager', 'Current version') . ')'
                                            : '' ?>
                                    </dt>
                                    <dd><?= $time ?></dd>
                                </dl>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row box box-solid">
                <div class="col-md-6">
                    <div>
                        <div class="box-header">
                            <h3 class="box-title"><?= Yii::t('extensions-manager', 'Description') ?></h3>
                        </div>
                        <div class="box-body">
                            <?= $description ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div>
                        <div class="box-header">
                            <h3 class="box-title"><?= Yii::t('extensions-manager', 'Dependencies') ?></h3>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <?php foreach ($dependencies as $type => $deps) : ?>
                                    <div class="col-sm-6">
                                        <div>
                                            <div class="box-header">
                                                <h3 class="box-title"><?= $type ?></h3>
                                            </div>
                                            <div class="box-body">
                                                <?php foreach ($deps as $repo => $version) : ?>
                                                    <dl class="dl-horizontal">
                                                        <dt><?= $repo ?></dt>
                                                        <dd><?= $version ?></dd>
                                                    </dl>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row box box-solid">
                <div class="col-sm-12">
                    <div>
                        <div class="box-header">
                            <h3 class="box-title"><?= Yii::t('extensions-manager', 'Readme') ?></h3>
                        </div>
                        <div class="box-body"><?= $readme ?></div>
                    </div>
                </div>
            </div>
        </div>
    </td>
</tr>