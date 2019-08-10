<?php

namespace crocone\cart;

use Yii;
use yii\base\Component;
use yii\base\InvalidParamException;
use crocone\cart\models\CartItemInterface;
use crocone\cart\storage\StorageInterface;

/**
 * Class Cart provides basic cart functionality (adding, removing, clearing, listing items). You can extend this class and
 * override it in the application configuration to extend/customize the functionality
 *
 * @package crocone\cart
 */
class Cart extends Component
{
    /**
     * @var string CartItemInterface class name
     */
    const ITEM_PRODUCT = '\crocone\cart\models\CartItemInterface';

    /**
     * Override this to provide custom (e.g. database) storage for cart data
     *
     * @var string|\crocone\cart\storage\StorageInterface
     */
    public $storageClass = '\crocone\cart\storage\SessionStorage';

    /**
     * @var array cart items
     */
    protected $items;

    /**
     * @var StorageInterface
     */
    private $_storage;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->clear(false);
        $this->setStorage(Yii::createObject($this->storageClass));
        $this->items = $this->storage->load($this);
    }

    /**
     * Assigns cart to logged in user
     *
     * @param string
     * @param string
     */
    public function reassign($sessionId, $userId)
    {
        if (get_class($this->getStorage()) === 'crocone\cart\storage\DatabaseStorage') {
            if (!empty($this->items)) {
                $storage = $this->getStorage();
                $storage->reassign($sessionId, $userId);
                self::init();
            }
        }
    }

    /**
     * Delete all items from the cart
     *
     * @param bool $save
     *
     * @return $this
     */
    public function clear($save = true): self
    {
        $this->items = [];
        $save && $this->storage->save($this);

        return $this;
    }

    /**
     * @return StorageInterface
     */
    public function getStorage(): StorageInterface
    {
        return $this->_storage;
    }

    /**
     * @param mixed $storage
     */
    public function setStorage($storage)
    {
        $this->_storage = $storage;
    }

    /**
     * Add an item to the cart
     *
     * @param models\CartItemInterface $element
     * @param bool $save
     *
     * @return $this
     */
    public function add(CartItemInterface $element, $save = true): self
    {
        $this->addItem($element);
        $save && $this->storage->save($this);

        return $this;
    }

    /**
     * @param \crocone\cart\models\CartItemInterface $item
     */
    protected function addItem(CartItemInterface $item)
    {
        $uniqueId = $item->getUniqueId();
	    if(isset($this->items[$uniqueId])){
		    $item->quantity = $this->items[$uniqueId]['quantity'] + $item->quantity;
	    }
        $this->items[$uniqueId] = $item;
    }

    /**
     * Removes an item from the cart
     *
     * @param string $uniqueId
     * @param bool $save
     *
     * @throws \yii\base\InvalidParamException
     *
     * @return $this
     */
    public function remove($uniqueId, $quantity, $save = true): self
    {
        if (!isset($this->items[$uniqueId])) {
            throw new InvalidParamException('Item not found');
        }
		if($quantity && $quantity > 0){
			$this->items[$uniqueId]->quantity = $quantity;
		}else{
            unset($this->items[$uniqueId]);
		}

        $save && $this->storage->save($this);

        return $this;
    }

    /**
     * @param string $itemType If specified, only items of that type will be counted
     *
     * @return int
     */
    public function getCount($itemType = null): int
    {
        return count($this->getItems($itemType));
    }
    
    
    /**
     * @param string $itemType If specified, only items of that type will be counted
     *
     * @return int
     */
    public function getSumm($itemType = null)
    {
        $items = $this->getItems($itemType);
    	$summ = 0;
    	foreach ($items as $item){
    		$summ += ($item['new_price'] ? $item['new_price'] : $item['price']) *  $item['quantity'];
	    }
    	 
        return $summ;
    }
	
    /**
     * @param string $itemType If specified, only items of that type will be counted
     *
     * @return int
     */
    public function getSummByOwner($owner,$itemType = null)
    {
    	$items = $this->getItemsByOwner($itemType)[$owner];
    	$summ = 0;
    	foreach ($items as $item){
    		$summ += ($item['new_price'] ? $item['new_price'] : $item['price']) *  $item['quantity'];
	    }
    	 
        return $summ;
    }

    /**
     * @param string $itemType If specified, only items of that type will be counted
     *
     * @return int
     */
    public function getWeightByOwner($owner,$itemType = null)
    {
    	$items = $this->getItemsByOwner($itemType)[$owner];
    	$summ = 0;
    	foreach ($items as $item){
    		$summ += is_int($item['weight']) ? $item['weight'] : 0 * $item['quantity'];
	    }
    	 
        return $summ;
    }

    /**
     * Returns all items of a given type from the cart
     *
     * @param string $itemType One of self::ITEM_ constants
     *
     * @return CartItemInterface[]
     */
    public function getItems($itemType = null): array
    {
        $items = $this->items;

        if (!is_null($itemType)) {
            $items = array_filter(
                $items,
                function ($item) use ($itemType) {
                    /* @var $item CartItemInterface */
                    return is_a($item, $itemType);
                }
            );
        }

        return $items;
    }
   
	
    /**
     * Returns all items of a given type from the cart and sort by owner
     *
     * @param string $itemType One of self::ITEM_ constants
     *
     * @return CartItemInterface[]
     */	
    public function getItemsByOwner($itemType = null): array {
	    $items = $this->items;
	    if (!is_null($itemType)) {
		    $items = array_filter(
			    $items,
			    function ($item) use ($itemType) {
				    /* @var $item CartItemInterface */
				    return is_a($item, $itemType);
			    }
		    );
	    }
	    $arr = array();
	    foreach ($items as $key => $item) {
		    $arr[$item['owner']][$key] = $item;
	    }
	    ksort($arr, SORT_NUMERIC);
	    
	    return $arr;
    }

    /**
     * Finds all items of type $itemType, sums the values of $attribute of all models and returns the sum.
     *
     * @param string $attribute
     * @param string|null $itemType
     *
     * @return int
     */
    public function getAttributeTotal($attribute, $itemType = null): int
    {
        $sum = 0;
        foreach ($this->getItems($itemType) as $model) {
            $sum += $model->{$attribute};
        }

        return $sum;
    }
}
