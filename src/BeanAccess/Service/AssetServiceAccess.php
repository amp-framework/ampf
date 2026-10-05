<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Service;

use ampf\BeanAccess\AbstractAccess;
use ampf\Service\Asset\AssetServiceInterface;

trait AssetServiceAccess
{
    use AbstractAccess;

    protected ?AssetServiceInterface $__assetService = null;

    public function getAssetService(): AssetServiceInterface
    {
        if ($this->__assetService === null) {
            $object = $this->getBeanFactory()->get(AssetServiceInterface::class);
            assert($object instanceof AssetServiceInterface);
            $this->setAssetService($object);
        }

        assert($this->__assetService instanceof AssetServiceInterface);

        return $this->__assetService;
    }

    public function setAssetService(AssetServiceInterface $object): void
    {
        $this->__assetService = $object;
    }
}
