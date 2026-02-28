<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ContactMessage;
use SzentirasHu\Http\Controllers\Controller;

class ContactMessageEditorController extends Controller
{
    public function index()
    {
        $messages = ContactMessage::rootMessages()
            ->with(['sender', 'replies.sender'])
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return view('editor.contactMessages.index', [
            'messages' => $messages,
        ]);
    }

    public function showThread($messageId)
    {
        $message = ContactMessage::with(['sender', 'replies.sender'])
            ->findOrFail($messageId);

        return view('editor.contactMessages.thread', [
            'message' => $message,
        ]);
    }

    public function reply($messageId): RedirectResponse
    {
        $validator = Validator::make(request()->all(), [
            'reply' => 'required|string|min:1|max:2000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $original = ContactMessage::findOrFail($messageId);
        $editorToken = Session::get('anonymous_token');
        $editor = AnonymousId::where('token', $editorToken)->first();

        if (!$editor) {
            abort(403);
        }

        // Determine receiver: if original is from a guest (no sender), cannot reply
        if ($original->isGuestMessage()) {
            abort(400, 'Cannot reply to guest messages');
        }

        $reply = ContactMessage::create([
            'sender_anonymous_id' => $editor->id,
            'receiver_anonymous_id' => $original->sender_anonymous_id,
            'parent_id' => $original->id,
            'message' => request('reply'),
        ]);

        return redirect()->route('editor.contactMessages.thread', $messageId)
            ->with('success', 'Reply sent.');
    }

    public function markResolved($messageId): RedirectResponse
    {
        $message = ContactMessage::findOrFail($messageId);
        $message->markAsResolved();

        return redirect()->back()->with('success', 'Thread marked as resolved.');
    }

    public function delete($messageId): RedirectResponse
    {
        $message = ContactMessage::findOrFail($messageId);
        $message->delete();

        return redirect()->route('editor.contactMessages.index')
            ->with('success', 'Message deleted.');
    }
}