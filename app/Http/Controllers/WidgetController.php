<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WidgetController extends Controller
{
    /**
     * Serves the dynamic, zero-dependency Vanilla JS widget script.
     */
    public function script(string $tenantId)
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return response('// Invalid tenant identifier', 404)
                ->header('Content-Type', 'application/javascript');
        }

        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenantId);

        $setting = TenantSetting::where('tenant_id', $tenantId)->first();
        
        // Find default target phone or first integrated tenant number
        $targetPhone = $setting->widget_target_phone ?? null;
        if ($targetPhone) {
            $exists = TenantNumber::where('tenant_id', $tenantId)
                ->where('integrated_number', $targetPhone)
                ->exists();
            if (!$exists) {
                $targetPhone = null;
            }
        }
        if (!$targetPhone) {
            $firstNumber = TenantNumber::where('tenant_id', $tenantId)->first();
            $targetPhone = $firstNumber->integrated_number ?? '';
        }

        // Safe JSON encoding to strictly prevent JS injection / XSS attacks!
        $safeConfig = json_encode([
            'tenantId' => $tenantId,
            'title' => $setting->widget_title ?? 'Chat with us on WhatsApp',
            'welcomeMsg' => $setting->widget_welcome_msg ?? 'Hi there! How can we help you today?',
            'color' => $setting->widget_color ?? '#00a884',
            'position' => $setting->widget_position ?? 'bottom-right',
            'autoRedirect' => (bool)($setting->widget_auto_redirect_wa ?? true),
            'targetPhone' => (string)$targetPhone,
            'submitUrl' => url('/api/v1/widget/' . $tenantId . '/submit'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $js = <<<JS
(function() {
    if (window.__WA_WIDGET_LOADED__) return;
    window.__WA_WIDGET_LOADED__ = true;

    var config = {$safeConfig};

    var style = document.createElement('style');
    style.textContent = `
        .wa-widget-btn {
            position: fixed;
            z-index: 999999;
            bottom: 20px;
            \${config.position === 'bottom-left' ? 'left: 20px;' : 'right: 20px;'}
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: \${config.color};
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .wa-widget-btn:hover { transform: scale(1.08); }
        .wa-widget-btn svg { width: 32px; height: 32px; fill: #ffffff; }

        .wa-widget-popup {
            position: fixed;
            z-index: 999999;
            bottom: 90px;
            \${config.position === 'bottom-left' ? 'left: 20px;' : 'right: 20px;'}
            width: 320px;
            max-width: calc(100vw - 40px);
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            display: none;
            flex-direction: column;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .wa-widget-popup.active { display: flex; }

        .wa-widget-header {
            background-color: \${config.color};
            color: #ffffff;
            padding: 16px;
            position: relative;
        }
        .wa-widget-header h4 { margin: 0; font-size: 15px; font-weight: 700; }
        .wa-widget-header p { margin: 4px 0 0 0; font-size: 12px; opacity: 0.9; }
        .wa-widget-close {
            position: absolute;
            top: 12px;
            right: 12px;
            background: none;
            border: none;
            color: #ffffff;
            font-size: 18px;
            cursor: pointer;
            opacity: 0.8;
        }

        .wa-widget-body { padding: 16px; }
        .wa-widget-input {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            box-sizing: border-box;
            outline: none;
        }
        .wa-widget-input:focus { border-color: \${config.color}; }

        .wa-widget-submit {
            width: 100%;
            padding: 12px;
            background-color: \${config.color};
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .wa-widget-submit:hover { opacity: 0.9; }
        .wa-widget-footer {
            text-align: center;
            font-size: 10px;
            color: #a0aec0;
            margin-top: 8px;
        }
    `;
    document.head.appendChild(style);

    var popup = document.createElement('div');
    popup.className = 'wa-widget-popup';

    var header = document.createElement('div');
    header.className = 'wa-widget-header';

    var closeBtn = document.createElement('button');
    closeBtn.className = 'wa-widget-close';
    closeBtn.innerHTML = '&times;';

    var titleElem = document.createElement('h4');
    titleElem.textContent = config.title;

    var msgElem = document.createElement('p');
    msgElem.textContent = config.welcomeMsg;

    header.appendChild(closeBtn);
    header.appendChild(titleElem);
    header.appendChild(msgElem);

    var form = document.createElement('form');
    form.className = 'wa-widget-body';

    var nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.className = 'wa-widget-input';
    nameInput.placeholder = 'Your Name';
    nameInput.required = true;

    var phoneInput = document.createElement('input');
    phoneInput.type = 'tel';
    phoneInput.className = 'wa-widget-input';
    phoneInput.placeholder = 'Phone Number (e.g. 9876543210)';
    phoneInput.required = true;

    var inquiryText = document.createElement('textarea');
    inquiryText.className = 'wa-widget-input';
    inquiryText.placeholder = 'How can we help?';
    inquiryText.rows = 2;

    var subBtn = document.createElement('button');
    subBtn.type = 'submit';
    subBtn.className = 'wa-widget-submit';
    subBtn.textContent = 'Start Chat';

    var footer = document.createElement('div');
    footer.className = 'wa-widget-footer';
    footer.textContent = '⚡ Powered by WhatsApp SaaS • By chatting you consent to receive messages';

    form.appendChild(nameInput);
    form.appendChild(phoneInput);
    form.appendChild(inquiryText);
    form.appendChild(subBtn);
    form.appendChild(footer);

    popup.appendChild(header);
    popup.appendChild(form);

    var btn = document.createElement('div');
    btn.className = 'wa-widget-btn';
    btn.innerHTML = '<svg viewBox="0 0 32 32"><path d="M16 2A13 13 0 0 0 4.68 21.27L3 27.5l6.38-1.66A13 13 0 1 0 16 2zm0 24a11 11 0 0 1-5.61-1.54l-.4-.24-3.79.99 1.01-3.69-.26-.41A11 11 0 1 1 16 26zm6.05-8.23c-.33-.17-1.96-.97-2.27-1.08-.31-.11-.53-.17-.75.17s-.86 1.08-1.05 1.3-.39.25-.72.08a9.12 9.12 0 0 1-2.67-1.65 10.07 10.07 0 0 1-1.85-2.3c-.19-.33 0-.51.15-.67.14-.14.33-.39.49-.58.17-.19.22-.33.33-.55.11-.22.06-.41-.03-.58s-.75-1.81-1.03-2.48c-.27-.65-.55-.56-.75-.57h-.64c-.22 0-.58.08-.88.41s-1.16 1.13-1.16 2.76 1.19 3.2 1.35 3.42 2.34 3.57 5.67 5.01c.79.34 1.41.55 1.89.7.79.25 1.51.22 2.08.13.63-.09 1.96-.8 2.24-1.57.28-.77.28-1.43.19-1.57-.08-.14-.3-.22-.63-.38z"/></svg>';

    document.body.appendChild(popup);
    document.body.appendChild(btn);

    btn.addEventListener('click', function() { popup.classList.toggle('active'); });
    closeBtn.addEventListener('click', function() { popup.classList.remove('active'); });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var name = nameInput.value;
        var phone = phoneInput.value;
        var inquiry = inquiryText.value;

        subBtn.disabled = true;
        subBtn.textContent = 'Connecting...';

        fetch(config.submitUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: name,
                phone_number: phone,
                message: inquiry,
                whatsapp_consent: true
            })
        }).then(function(res) {
            if (!res.ok) {
                return res.json().then(function(errData) {
                    throw new Error(errData.error || errData.message || 'Submission failed');
                });
            }
            return res.json();
        }).then(function(data) {
            subBtn.textContent = 'Sent!';
            setTimeout(function() {
                popup.classList.remove('active');
                subBtn.disabled = false;
                subBtn.textContent = 'Start Chat';
                form.reset();
            }, 1000);

            if (config.autoRedirect && config.targetPhone) {
                var waText = encodeURIComponent('Hi, my name is ' + name + '. ' + inquiry);
                window.open('https://wa.me/' + config.targetPhone + '?text=' + waText, '_blank');
            }
        }).catch(function(err) {
            alert(err.message || 'Failed to connect. Please try again.');
            subBtn.disabled = false;
            subBtn.textContent = 'Start Chat';
        });
    });
})();
JS;

        return response($js, 200)->header('Content-Type', 'application/javascript');
    }

    /**
     * CORS-enabled public endpoint for website lead submissions.
     */
    public function submit(Request $request, string $tenantId, \App\Services\ContactIngestionService $ingestionService)
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return $this->corsResponse(['error' => 'Tenant not found.'], 404);
        }

        // Bind tenant ID strictly from URL parameter, ignoring any request body tampering
        app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

        $validated = $request->validate([
            'phone_number'     => 'required|string|max:50',
            'name'             => 'nullable|string|max:255',
            'message'          => 'nullable|string|max:1000',
            'whatsapp_consent' => 'nullable|boolean',
        ]);

        try {
            $contact = $ingestionService->ingest($tenant->id, array_merge($validated, [
                'source' => 'Website WhatsApp Widget',
                'whatsapp_consent' => $validated['whatsapp_consent'] ?? true,
            ]));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->corsResponse(['error' => 'Invalid phone number format.'], 422);
        }

        // Security & Data Hygiene: Return minimal success status, never leak full Eloquent model
        return $this->corsResponse([
            'success' => true,
            'message' => $contact->wasRecentlyCreated ? 'Lead captured successfully.' : 'Lead updated successfully.',
        ], $contact->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * OPTIONS Preflight handler for cross-origin CORS.
     */
    public function options(string $tenantId)
    {
        return $this->corsResponse([], 200);
    }

    /**
     * Helper to return CORS-compliant JSON responses.
     */
    private function corsResponse(array $data, int $status)
    {
        return response()->json($data, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }
}
