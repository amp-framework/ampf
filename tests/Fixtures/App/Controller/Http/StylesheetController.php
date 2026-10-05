<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Http;

use ampf\BeanAccess\Service\AssetServiceAccess;
use ampf\Controller\Http\AbstractController;

/** The address of the stylesheet with its version, as a page of an application writes it into a link element. */
final class StylesheetController extends AbstractController
{
    use AssetServiceAccess;

    public function execute(): void
    {
        $this->getRequest()->setResponse($this->getAssetService()->link($this->getRequest(), 'css/app.css'));
    }
}
