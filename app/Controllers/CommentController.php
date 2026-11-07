<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Services\Comments;

final class CommentController extends Controller
{
    public function store(): void
    {
        $entity = (string) input('entity');
        $id = (int) input('entity_id');
        $body = trim((string) input('body'));
        if (!in_array($entity, Comments::ENTITIES, true) || !$id || $body === '' || mb_strlen($body) > 4000) {
            abort(422);
        }
        if (!in_scope(Comments::locationOf($entity, $id))) {
            abort(403);
        }
        Comments::add($entity, $id, $body);
        if (wants_json()) {
            json_response(['ok' => true]);
        }
        redirect(parse_url(Comments::target($entity, $id)[1], PHP_URL_PATH) . '#comments');
    }

    public function destroy(string $id): void
    {
        $c = db()->one('SELECT * FROM comments WHERE id = ?', [(int) $id]) ?? abort(404);
        if ((int) $c['user_id'] !== Auth::id() && !can('settings.manage')) {
            abort(403);
        }
        db()->delete('comments', (int) $id);
        back();
    }
}
