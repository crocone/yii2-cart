<?php

namespace crocone\cart\storage;

use Yii;
use yii\base\BaseObject;
use crocone\cart\Cart;

/**
 * Class SessionStorage is a session adapter for cart data storage.
 *
 * @property \yii\web\Session session
 */
class SessionStorage extends BaseObject implements StorageInterface
{
    /**
     * @var string
     */
    public $key = 'cart';

    /**
     * @inheritdoc
     */
    public function load(Cart $cart)
    {
        $cartData = [];

        if (false !== ($session = ($this->session->get($this->key, false)))) {
            $cartData = unserialize(base64_decode($session));
        }

        return $cartData;
    }

    /**
     * @inheritdoc
     */
    public function save(Cart $cart)
    {
        $sessionData = base64_encode(serialize($cart->getItems()));

        $this->session->set($this->key, $sessionData);
    }

    /**
     * @return object
     */
    public function getSession()
    {
        return Yii::$app->get('session');
    }
}
