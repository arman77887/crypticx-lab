<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendContactMessageRequest;
use App\Mail\ContactMessageMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactController extends Controller
{
    public function store(
        SendContactMessageRequest $request
    ): JsonResponse {
        $recipient = config('contact.recipient');

        if (! is_string($recipient) || $recipient === '') {
            Log::error('Contact recipient is not configured.');

            return response()->json([
                'success' => false,
                'message' => 'Contact service is temporarily unavailable.',
            ], 503);
        }

        $data = $request->validated();

        unset($data['website']);

        $data['ip'] = $request->ip();
        $data['user_agent'] = substr(
            (string) $request->userAgent(),
            0,
            500
        );

        try {
            Mail::to($recipient)
                ->send(new ContactMessageMail($data));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Message could not be sent. Please try again later.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent successfully.',
        ], 201);
    }
}
