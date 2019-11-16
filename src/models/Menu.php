<?php

namespace diecoding\rbac\models;

use Yii;
use yii\db\Query;
use mdm\admin\components\Configs;
use diecoding\rbac\Module;

/**
 * This is the model class for table "{{%auth_menu}}".
 *
 * @property int $id
 * @property string $name
 * @property int $parent
 * @property string $route
 * @property string $visible
 * @property string $icon
 * @property int $order
 * @property string $options
 * @property string $data For more information to this menu
 *
 * @property Menu $menuParent
 * @property Menu[] $menuChildren 
 *  
 * @author Die Coding (Sugeng Sulistiyawan) <diecoding@gmail.com>
 * @copyright 2019 Die Coding
 * @license MIT
 * @link https://www.diecoding.com
 * @version 0.0.1
 */
class Menu extends \yii\db\ActiveRecord
{
    public $parent_name;

    private static $_routes;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        $config = new Module(null);

        return $config->menuTable;
    }

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        if (Configs::instance()->db !== null) {
            return Configs::instance()->db;
        } else {
            return parent::getDb();
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [
                ['parent_name'], 'in',
                'range' => static::find()->select(['name'])->column(),
                'message' => 'Menu "{value}" not found.',
            ],
            [['parent', 'route', 'visible', 'icon', 'order', 'options', 'data'], 'default'],
            [['parent'], 'filterParent', 'when' => function () {
                return !$this->isNewRecord;
            }],
            [['order'], 'integer'],
            [
                ['route'], 'in',
                'range' => static::getSavedRoutes(),
                'message' => 'Route "{value}" not found.',
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => Yii::t('diecoding-rbac', 'Name'),
            'parent' => Yii::t('diecoding-rbac', 'Parent'),
            'route' => Yii::t('diecoding-rbac', 'Route'),
            'visible' => Yii::t('diecoding-rbac', 'Visible'),
            'icon' => Yii::t('diecoding-rbac', 'Icon'),
            'order' => Yii::t('diecoding-rbac', 'Order'),
            'options' => Yii::t('diecoding-rbac', 'Options'),
            'data' => Yii::t('diecoding-rbac', 'Data'),
        ];
    }

    /**
     * Use to loop detected.
     */
    public function filterParent()
    {
        $parent = $this->parent;
        $db     = static::getDb();
        $query  = (new Query)->select(['parent'])
            ->from(static::tableName())
            ->where('[[id]]=:id');
        while ($parent) {
            if ($this->id == $parent) {
                $this->addError('parent_name', 'Loop detected.');
                return;
            }
            $parent = $query->params([':id' => $parent])->scalar($db);
        }
    }

    /**
     * Handle eval() code
     *
     * @param string $code
     * @param boolean $print
     * @return mixed
     */
    public static function eval($code, $print = false)
    {
        try {
            $result = eval($code);
            $error  = false;
            $value  = $result;
        } catch (\ParseError $e) {
            $result = false;
            $value  = "Error: Input code";
            $error  = $e;
        }

        if ($print) {
            $label = [
                Yii::t('diecoding-rbac', 'VALUE: (value used for this [visible])'),
                Yii::t('diecoding-rbac', 'INPUT: (raw input)'),
                Yii::t('diecoding-rbac', 'OUTPUT: (raw output)'),
            ];
            $result = <<< HTML
<style>
pre {
    white-space: pre-wrap;       /* Since CSS 2.1 */
    white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
    white-space: -pre-wrap;      /* Opera 4-6 */
    white-space: -o-pre-wrap;    /* Opera 7 */
    word-wrap: break-word;       /* Internet Explorer 5.5+ */
}
</style>

<code>{$label[0]}</code>
<pre>$value</pre>

<code>{$label[1]}</code>
<pre>$code</pre>
HTML;

            if ($error !== false) {
                $result .= <<< HTML

<code>{$label[2]}</code>
<pre>$error</pre>
HTML;
            }
        }

        return $result;
    }

    /**
     * Get menu parent
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenuParent()
    {
        return $this->hasOne(static::class, ['id' => 'parent']);
    }

    /**
     * Get menu children
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenuChildren()
    {
        return $this->hasMany(static::class, ['parent' => 'id']);
    }

    /**
     * Get saved routes.
     * @return array
     */
    public static function getSavedRoutes()
    {
        if (self::$_routes === null) {
            self::$_routes = [];
            foreach (Configs::authManager()->getPermissions() as $name => $value) {
                if ($name[0] === '/' && substr($name, -1) != '*') {
                    self::$_routes[] = $name;
                }
            }
        }
        return self::$_routes;
    }

    public static function getMenuSource()
    {
        $tableName = static::tableName();
        return (new \yii\db\Query())
            ->select(['m.id', 'm.name', 'm.route', 'parent_name' => 'p.name'])
            ->from(['m' => $tableName])
            ->leftJoin(['p' => $tableName], '[[m.parent]]=[[p.id]]')
            ->all(static::getDb());
    }
}
