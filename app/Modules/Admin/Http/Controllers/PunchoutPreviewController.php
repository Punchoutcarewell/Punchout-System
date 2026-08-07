<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Punchout\Cxml\XmlSecurity;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * The browser_form_post_url a preview session (see
 * SessionManager::startPreview()) points at, instead of a real Coupa
 * checkout URL that does not exist. An admin clicking all the way through
 * a storefront preview to "Transfer cart to Coupa" lands here rather than
 * a browser error, seeing the same cXML PunchOutOrderMessage a real
 * transfer would have sent Coupa. No punchout session or credential
 * validation happens here, only an authenticated admin ever reaches this
 * URL, see the "auth" middleware on this route.
 */
final class PunchoutPreviewController
{
    public function complete(Request $request): View
    {
        $rawCxml = (string) $request->input('cxml-urlencoded', '');

        return view('admin.punchout-preview-complete', [
            'cxml' => $this->prettyPrint($rawCxml),
        ]);
    }

    private function prettyPrint(string $rawCxml): string
    {
        try {
            $document = XmlSecurity::loadSafely($rawCxml);
            $document->formatOutput = true;

            return (string) $document->saveXML();
        } catch (Throwable) {
            // Only ever reached if this endpoint receives something that
            // is not well-formed cXML, which should not happen since it
            // only ever sees what OrderMessageBuilder itself produced;
            // shown as-is rather than failing the whole preview page.
            return $rawCxml;
        }
    }
}
