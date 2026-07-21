<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Enum;

/**
 * Identifies a lifecycle e-mail. Each key ships with a Dutch default subject and
 * body (inner HTML, wrapped in the branded shell at render time). An owner can
 * override subject/body per key via an {@see \VanDerSangen\ProjectTemplateBundle\Mail\Entity\EmailTemplate}.
 *
 * Placeholders use {{ name }} syntax and are filled from the render context
 * (branding + per-event data). Unknown placeholders are stripped.
 */
enum EmailTemplateKey: string
{
    case SubscriptionActivated = 'subscription_activated';
    case SubscriptionRecovered = 'subscription_recovered';
    case PaymentReceived = 'payment_received';
    case SubscriptionPastDue = 'subscription_past_due';
    case SubscriptionVerificationFailed = 'subscription_verification_failed';
    case SubscriptionCancelled = 'subscription_cancelled';
    case SubscriptionEnded = 'subscription_ended';
    case SubscriptionPendingCancellation = 'subscription_pending_cancellation';
    case SubscriptionPlanChangeScheduled = 'subscription_plan_change_scheduled';

    // Account-/systeemmails (niet-abonnement). Verstuurd door de consumerende tool
    // via een branded kanaal, met dezelfde shell als de lifecycle-mails.
    case Welcome = 'welcome';
    case PasswordReset = 'password_reset';
    case PasswordResetConfirmation = 'password_reset_confirmation';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Welkom',
            self::PasswordReset => 'Wachtwoord opnieuw instellen',
            self::PasswordResetConfirmation => 'Wachtwoord gewijzigd',
            self::SubscriptionActivated => 'Abonnement geactiveerd',
            self::SubscriptionRecovered => 'Betaling hersteld',
            self::PaymentReceived => 'Betaling ontvangen',
            self::SubscriptionPastDue => 'Betaling mislukt',
            self::SubscriptionVerificationFailed => 'Verificatiebetaling mislukt',
            self::SubscriptionCancelled => 'Abonnement opgezegd',
            self::SubscriptionEnded => 'Abonnement beëindigd',
            self::SubscriptionPendingCancellation => 'Opzegging ingepland',
            self::SubscriptionPlanChangeScheduled => 'Wijziging abonnement ingepland',
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::Welcome => 'Welkom bij {{ toolName }}',
            self::PasswordReset => 'Stel je wachtwoord opnieuw in',
            self::PasswordResetConfirmation => 'Je wachtwoord is gewijzigd',
            self::SubscriptionActivated => 'Je abonnement is nu actief',
            self::SubscriptionRecovered => 'Je abonnement loopt weer',
            self::PaymentReceived => 'Betaling ontvangen — bedankt!',
            self::SubscriptionPastDue => 'Betaling mislukt — actie vereist',
            self::SubscriptionVerificationFailed => 'Verificatiebetaling mislukt — actie vereist',
            self::SubscriptionCancelled => 'Je abonnement is opgezegd',
            self::SubscriptionEnded => 'Je abonnement is beëindigd',
            self::SubscriptionPendingCancellation => 'Je opzegging is ingepland',
            self::SubscriptionPlanChangeScheduled => 'Je wijziging is ingepland',
        };
    }

    /**
     * Dutch default inner HTML. Wrapped in the branded shell at render time, so
     * this contains only the message body — no <html>/<body>/branding chrome.
     */
    public function defaultBodyHtml(): string
    {
        return match ($this) {
            self::Welcome =>
                '<h1>Welkom, {{ customerName }}!</h1>'
                . '<p>Je account bij {{ toolName }} is aangemaakt. Fijn dat je er bent!</p>'
                . '<p>Je kunt vanaf nu inloggen en aan de slag.</p>',
            self::PasswordReset =>
                '<h1>Wachtwoord opnieuw instellen</h1>'
                . '<p>Hallo {{ customerName }}, we ontvingen een verzoek om je wachtwoord opnieuw '
                . 'in te stellen.</p>'
                . '<p><a href="{{ resetUrl }}">Klik hier om een nieuw wachtwoord te kiezen</a>. '
                . 'Deze link verloopt over {{ expiry }}.</p>'
                . '<p>Heb je dit niet aangevraagd? Dan kun je deze e-mail negeren; je wachtwoord '
                . 'blijft ongewijzigd.</p>',
            self::PasswordResetConfirmation =>
                '<h1>Je wachtwoord is gewijzigd</h1>'
                . '<p>Hallo {{ customerName }}, je wachtwoord is succesvol gewijzigd.</p>'
                . '<p>Was jij dit niet? Neem dan direct contact met ons op.</p>',
            self::SubscriptionActivated =>
                '<h1>Abonnement geactiveerd</h1>'
                . '<p>Je abonnement is nu actief. Bedankt voor je betaling!</p>'
                . '<p><strong>Volgende incassodatum:</strong> {{ nextBillingDate }}</p>',
            self::SubscriptionRecovered =>
                '<h1>Betaling hersteld</h1>'
                . '<p>Goed nieuws — we hebben je openstaande betaling geïncasseerd en je '
                . 'abonnement is weer actief.</p>'
                . '<p><strong>Volgende incassodatum:</strong> {{ nextBillingDate }}</p>',
            self::PaymentReceived =>
                '<h1>Betaling ontvangen</h1>'
                . '<p>Bedankt voor je betaling! Dit is je bevestiging.</p>'
                . '<p><strong>Referentie:</strong> {{ reference }}<br>'
                . '<strong>Datum:</strong> {{ paymentDate }}<br>'
                . '<strong>Omschrijving:</strong> {{ description }}<br>'
                . '<strong>Bedrag:</strong> {{ amount }}</p>'
                . '<p>Bewaar deze e-mail voor je administratie.</p>',
            self::SubscriptionPastDue =>
                '<h1>Betaling mislukt</h1>'
                . '<p>Het is ons niet gelukt om je abonnementsbetaling te verwerken.</p>'
                . '<p>We proberen de betaling de komende dagen automatisch opnieuw. Controleer '
                . 'je bankgegevens of werk je betaalmethode bij om onderbreking te voorkomen.</p>',
            self::SubscriptionVerificationFailed =>
                '<h1>Verificatiebetaling mislukt</h1>'
                . '<p>We konden je verificatiebetaling niet verwerken. Je abonnement is niet '
                . 'geactiveerd.</p>'
                . '<p>Probeer het opnieuw om je aanmelding af te ronden.</p>',
            self::SubscriptionCancelled =>
                '<h1>Abonnement opgezegd</h1>'
                . '<p>Je abonnement is opgezegd.</p>'
                . '<p>Jammer dat je gaat. Je kunt op elk moment opnieuw abonneren.</p>',
            self::SubscriptionEnded =>
                '<h1>Abonnement beëindigd</h1>'
                . '<p>Je abonnement is zoals gepland beëindigd. Bedankt dat je klant was!</p>'
                . '<p>Je kunt je op elk moment opnieuw abonneren — je gegevens staan nog voor je klaar.</p>',
            self::SubscriptionPendingCancellation =>
                '<h1>Opzegging ingepland</h1>'
                . '<p>Je abonnement is ingepland voor opzegging.</p>'
                . '<p>Je houdt toegang tot <strong>{{ endsAt }}</strong>.</p>',
            self::SubscriptionPlanChangeScheduled =>
                '<h1>Wijziging ingepland</h1>'
                . '<p>Je wijziging is ingepland. Na je volgende incasso op <strong>{{ nextBillingDate }}</strong> '
                . 'stapt je abonnement over op het nieuwe plan.</p>'
                . '<p><strong>Nieuw plan:</strong> {{ newPlanAmount }} / {{ newPlanInterval }}</p>'
                . '<p>Je huidige toegang blijft actief tot de wijziging ingaat.</p>',
        };
    }
}
