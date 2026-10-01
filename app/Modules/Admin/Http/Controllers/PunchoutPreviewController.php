<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Punchout\Cxml\XmlSecurity;
use App\Modules\Punchout\Models\PunchoutSession;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * The browser_form_post_url a preview session (see
 * SessionManager::startPreview()) points at, instead of a real Coupa
 * checkout URL that does not exist. A preview is a whole transaction on
 * its own generated session: whoever holds the preview link can browse,
 * fill the cart and click "Transfer cart to Coupa", and lands here
 * without ever being asked to log in to Admin.
 *
 * The response is the raw cXML PunchOutOrderMessage, served as text/xml
 * exactly as a real Coupa endpoint would receive it, not an HTML page
 * wrapped around it.
 *
 * Authorisation is the session itself rather than an admin login: the
 * posted cXML must carry the BuyerCookie of a session that is meant to
 * return here, either a preview session (see SessionManager::startPreview()
 * and is_preview) or a session whose own return URL was deliberately set to
 * this endpoint, which is how a credential is pointed back at this app for
 * testing before Coupa supplies its real checkout URL. A normal buyer
 * session returns to Coupa's URL and never matches. Nothing is stored or
 * sent anywhere, so the only thing this endpoint can ever do is echo back a
 * message the app itself built for such a session, it is not a general
 * purpose XML echo.
 */
final class PunchoutPreviewController
{
    public function complete(Request $request): Response
    {
        $rawCxml = (string) $request->input('cxml-urlencoded', '');

        $document = $this->parse($rawCxml);

        if ($document === null) {
            return $this->fault(400, 'Malformed request.');
        }

        $buyerCookie = trim((string) (new DOMXPath($document))->evaluate('string(//BuyerCookie)'));

        $isPreviewSession = $buyerCookie !== ''
            && PunchoutSession::query()
                ->where('buyer_cookie', $buyerCookie)
                ->where(fn ($query) => $query
                    ->where('is_preview', true)
                    ->orWhere('browser_form_post_url', 'like', '%/admin/punchout-preview/complete'))
                ->exists();

        if (! $isPreviewSession) {
            return $this->fault(403, 'Not a session that returns to this endpoint.');
        }

        $document->formatOutput = true;

        return response((string) $document->saveXML(), 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    private function parse(string $rawCxml): ?\DOMDocument
    {
        if (trim($rawCxml) === '') {
            return null;
        }

        try {
            return XmlSecurity::loadSafely($rawCxml);
        } catch (Throwable) {
            return null;
        }
    }

    private function fault(int $code, string $text): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">'
            ."<cXML><Response><Status code=\"{$code}\" text=\"{$text}\"/></Response></cXML>";

        return response($xml, $code)->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
