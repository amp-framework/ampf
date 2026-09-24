<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Http;

use ampf\Controller\Http\AbstractController;
use ampf\Tests\Fixtures\App\BeanAccess\Doctrine\Repository\NoteRepoAccess;

/**
 * The notes of the database and a form for another: a POST with the request's token stores it and redirects back
 * to the list; one without is forbidden.
 */
final class NotesController extends AbstractController
{
    use NoteRepoAccess;

    public function execute(): void
    {
        $request = $this->getRequest();

        if (!$request->isPostRequest()) {
            $request->setResponse($this->getView()->subRender('notes.html.php', [
                'notes' => $this->getNoteRepo()->findInOrder(),
            ]));

            return;
        }

        if (!$request->hasCorrectToken()) {
            $request->setStatusCode(403)->setResponse('Forbidden');

            return;
        }

        $this->getNoteRepo()->add($request->getPostString('text'));
        $request->setRedirect('notes', null, 303);
    }
}
