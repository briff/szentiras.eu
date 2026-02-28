<?php

namespace SzentirasHu\Http\Controllers\Contact;

use Illuminate\Support\Facades\Session;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ContactMessage;
use SzentirasHu\Http\Controllers\Controller;

class InboxController extends Controller
{
    public function index()
    {
        $token = Session::get('anonymous_token');
        if (!$token) {
            return redirect()->route('contact.form');
        }

        $anonymousId = AnonymousId::where('token', $token)->first();
        if (!$anonymousId) {
            return redirect()->route('contact.form');
        }

        $messages = ContactMessage::forReceiver($anonymousId)
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Mark unread messages as read when viewing inbox
        $unreadIds = $messages->filter(fn($msg) => !$msg->is_read)->pluck('id');
        if ($unreadIds->isNotEmpty()) {
            ContactMessage::whereIn('id', $unreadIds)->update(['is_read' => true]);
        }

        return view('contact.inbox', [
            'messages' => $messages,
            'anonymousId' => $anonymousId,
        ]);
    }

    public function showThread($messageId)
    {
        $token = Session::get('anonymous_token');
        if (!$token) {
            return redirect()->route('contact.form');
        }

        $anonymousId = AnonymousId::where('token', $token)->first();
        if (!$anonymousId) {
            return redirect()->route('contact.form');
        }

        $message = ContactMessage::with(['sender', 'receiver', 'replies.sender'])
            ->findOrFail($messageId);

        // Ensure user is either sender or receiver of this thread
        $isParticipant = $message->sender_anonymous_id === $anonymousId->id
            || $message->receiver_anonymous_id === $anonymousId->id;

        if (!$isParticipant) {
            abort(403);
        }

        // Mark as read if it's a received message
        if ($message->receiver_anonymous_id === $anonymousId->id && !$message->is_read) {
            $message->markAsRead();
        }

        return view('contact.thread', [
            'message' => $message,
            'anonymousId' => $anonymousId,
        ]);
    }
}