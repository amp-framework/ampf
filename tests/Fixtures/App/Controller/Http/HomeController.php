<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Http;

use ampf\BeanAccess\Service\TranslatorServiceAccess;
use ampf\Controller\Http\AbstractController;

/** The home page: a translated welcome and the links of two routes, in the layout. */
final class HomeController extends AbstractController
{
    use TranslatorServiceAccess;

    public function execute(): void
    {
        $this->getTranslatorService()->setLanguage('en');

        $this->getRequest()->setResponse($this->getView()->subRender('layout.html.php', [
            'title' => 'Home',
            'content' => $this->getView()->subRender('home.html.php', ['name' => 'world']),
        ]));
    }
}
