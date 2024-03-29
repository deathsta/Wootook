<?php

abstract class Wootook_Empire_Model_BuilderAbstract
    implements Countable, Serializable, Iterator
{
    /**
     * Planet instance
     * @var Wootook_Empire_Model_Planet
     */
    protected $_currentPlanet = null;

    /**
     * User instance
     * @var Wootook_Empire_Model_User
     */
    protected $_currentUser = null;

    /**
     * construction queue
     * @var array
     */
    protected $_queue = null;

    /**
     * construction queue
     * @var array
     */
    protected $_itemClass = 'Wootook_Empire_Model_Builder_Item';

    /**
     *
     * @param Wootook_Empire_Model_Planet $currentPlanet
     * @param Wootook_Empire_Model_User $currentUser
     */
    public function __construct(Wootook_Empire_Model_Planet $currentPlanet, Wootook_Empire_Model_User $currentUser)
    {
        $this->_currentPlanet = $currentPlanet;
        $this->_currentUser = $currentUser;

        $this->init();
    }

    abstract public function init();

    abstract protected function _initItem(Array $params);

    public function enqueue($params, $index = null)
    {
        if ($this->_currentUser->getVacation()) {
            return $this;
        }

        $item = $this->_initItem($params);
        if ($item === null) {
            return $this;
        }

        if ($index === null) {
            $index = $this->_generateIndex();
        }
        $item->setIndex($index);
        $this->_queue[$index] = $item;

        return $this;
    }

    public function dequeue($item)
    {
        unset($this->_queue[$item->getIndex()]);

        return $this;
    }

    protected function _generateIndex()
    {
        return uniqid();
    }

    public function getItem($itemIndex)
    {
        if (isset($this->_queue[$itemIndex])) {
            return $this->_queue[$itemIndex];
        }

        return null;
    }

    protected function _serializeQueue()
    {
        $serialize = array();
        foreach ($this->_queue as $itemIndex => $itemInstance) {
            $serialize[$itemIndex] = $itemInstance->getAllDatas();
        }
        return serialize($serialize);
    }

    protected function _unserializeQueue($serialized)
    {
        $this->clearQueue();

        $unserialized = @unserialize($serialized);
        if ($unserialized === false) {
            $this->_queue = array();
            return $this;
        }

        foreach ($unserialized as $itemIndex => $itemData) {
            $this->enqueue($itemData, $itemIndex);
        }

        return $this;
    }

    public function __toString()
    {
        return $this->_serializeQueue();
    }

    public function getQueue()
    {
        return $this->_queue;
    }

    public function clearQueue()
    {
        $this->_queue = array();
    }

    abstract public function updateQueue($time);
    abstract public function appendQueue($typeId, $qty, $time);

    /**
     * Check if an item type is actually buildable on the current
     * planet, depending on the technology and buildings requirements.
     *
     * @param int $typeId
     * @return bool
     */
    public function checkAvailability($typeId)
    {
        $types = Wootook_Empire_Model_Game_Types::getSingleton();
        $requirements = Wootook_Empire_Model_Game_Requirements::getSingleton();

        if (!isset($requirements[$typeId]) || empty($requirements[$typeId])) {
            return true;
        }

        foreach ($requirements[$typeId] as $requirement => $level) {
            if ($types->is($requirement, Legacies_Empire::TYPE_BUILDING) && $this->_currentPlanet->hasElement($requirement, $level)) {
                continue;
            } else if ($types->is($requirement, Legacies_Empire::TYPE_RESEARCH) && $this->_currentUser->hasElement($requirement, $level)) {
                continue;
            } else if ($types->is($requirement, Legacies_Empire::TYPE_DEFENSE) && $this->_currentPlanet->hasElement($requirement, $level)) {
                continue;
            } else if ($types->is($requirement, Legacies_Empire::TYPE_SHIP) && $this->_currentPlanet->hasElement($requirement, $level)) {
                continue;
            }
            return false;
        }

        return true;
    }

    abstract public function getResourcesNeeded($typeId, $level);
    abstract public function getBuildingTime($typeId, $level);

    public function serialize()
    {
        return $this->_serializeQueue();
    }

    public function unserialize($serialized)
    {
        $this->_unserializeQueue($serialized);
    }

    public function count()
    {
        return count($this->_queue);
    }

    public function current()
    {
        return current($this->_queue);
    }

    public function next()
    {
        return next($this->_queue);
    }

    public function key()
    {
        return key($this->_queue);
    }

    public function valid()
    {
        return current($this->_queue) !== false;
    }

    public function rewind()
    {
        reset($this->_queue);
    }

    public function getCurrentPlanet()
    {
        return $this->_currentPlanet;
    }

    public function getCurrentUser()
    {
        return $this->_currentUser;
    }

    public function setCurrentPlanet(Wootook_Empire_Model_Planet $planet)
    {
        $this->_currentPlanet = $planet;

        return $this;
    }

    public function setCurrentUser(Wootook_Empire_Model_User $user)
    {
        $this->_currentUser = $user;

        return $this;
    }

    protected function _calculateResourceRemainingAmounts($resourceNeeded)
    {
        $resourceAmounts = array();
        foreach ($resourceNeeded as $resourceId => $resourceAmount) {
            $resourceAmounts[$resourceId] = Math::sub($this->_currentPlanet[$resourceId], $resourceAmount);
            if (Math::isNegative($resourceAmounts[$resourceId])) {
                return false;
            }
        }
        return $resourceAmounts;
    }

    protected function _calculateResourceReclaimedAmounts($resourceNeeded)
    {
        $resourceAmounts = array();
        foreach ($resourceNeeded as $resourceId => $resourceAmount) {
            $resourceAmounts[$resourceId] = Math::add($this->_currentPlanet[$resourceId], $resourceAmount);
        }
        return $resourceAmounts;
    }
}