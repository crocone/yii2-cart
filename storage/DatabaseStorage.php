<?php

namespace crocone\cart\storage;

use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Query;
use yii\web\User;
use crocone\cart\Cart;

/**
 * Class DatabaseStorage is a database adapter for cart data storage.
 *
 * If userComponent is set, it tries to call getId() from the component and use the result as user identifier. If it
 * fails, or if $userComponent is not set, it will use sessionId as user identifier
 *
 * @package crocone\cart\storage
 */
class DatabaseStorage extends BaseObject implements StorageInterface
{
    /**
     * @var string Name of the user component
     */
    public $userComponent = 'user';

    /**
     * @var string Name of the database component
     */
    public $dbComponent = 'db';

    /**
     * @var string Name of the cart table
     */
    public $table = '{{%cart}}';

    /**
     * @var string Name of the
     */
    public $idField = 'sessionId';

    /**
     * @var string Name of the field holding serialized session data
     */
    public $dataField = 'cartData';

    /**
     * @var bool If set to true, empty cart entries will be deleted
     */
    public $deleteIfEmpty = false;

    /**
     * @var Connection
     */
    private $_db;

    /**
     * @var User
     */
    private $_user;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->_db = Yii::$app->get($this->dbComponent);

        if ($this->userComponent !== null) {
            $this->_user = Yii::$app->get($this->userComponent);
        }

        if ($this->table === null) {
            throw new InvalidConfigException('Please specify "table" in cart configuration');
        }
    }

    /**
     * @param Cart $cart
     *
     * @return mixed
     */
    public function load(Cart $cart)
    {
        $items = [];
        $identifier = $this->getIdentifier(Yii::$app->session->getId());

        $query = new Query();
        $query->select($this->dataField)
            ->from($this->table)
            ->where([$this->idField => $identifier]);

        if ($data = $query->createCommand($this->_db)->queryScalar()) {
            $items = unserialize(base64_decode($data));
        }

        return $items;
    }

    /**
     * @param int $default
     *
     * @return int
     */
    protected function getIdentifier($default)
    {
        $id = $default;

        if ($this->_user instanceof User && !$this->_user->getIsGuest()) {
            $id = $this->_user->getId();
        }

        return $id;
    }

    /**
     * @param \crocone\cart\Cart $cart
     */
    public function save(Cart $cart)
    {
        $identifier = $this->getIdentifier(Yii::$app->session->getId());

        $items = $cart->getItems();
	   
        $sessionData = base64_encode(serialize($items));

        $command = $this->_db->createCommand();

        if (empty($items) && true === $this->deleteIfEmpty) {
            $command->delete($this->table, [$this->idField => $identifier]);
	        $command->execute();
        } else {
        	$model = \common\models\Cart::findOne([$this->idField => $identifier]);
        	if(!$model){
        		$model = new \common\models\Cart();
        		$model->sessionId = $identifier;
	        }
        	$model->cartData = $sessionData;
        	if(!$model->save()){
        		print_r($model->getErrors());
	        }
        }
        
    }

    /**
     * Assigns cart to logged in user
     *
     * @param $sourceId
     * @param $destinationId
     */
    public function reassign($sourceId, $destinationId)
    {
        $command = $this->_db->createCommand();

        $command->delete($this->table, [$this->idField => $destinationId])->execute();

        $command->update($this->table, [$this->idField => $destinationId], [$this->idField => $sourceId])->execute();
    }
}
