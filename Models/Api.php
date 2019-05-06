<?php
 
namespace BilliePayment\Models;
 
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\Model\ModelEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="billie_api")
 */
class Api extends ModelEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    private $state;
    
    /**
     * @ORM\ManyToOne(targetEntity="\Shopware\Models\Order\Order")
     * @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     */
    protected $order;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param mixed $state
     */
    public function setState($state)
    {
        $this->state = $state;
    }
 
    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }
    
    /**
     * @param mixed $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }
}
