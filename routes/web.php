<?php
declare(strict_types=1);

/**
 * Registers all web routes for the application.
 *
 * Routes are grouped here so bootstrap code only needs to load and execute one closure.
 */
return static function (\core\router $router): void {
    $router->get("", "controllers\home@index");

    $router->get("login", "controllers\auth@login");
    $router->post("login", "controllers\auth@authenticate");
    $router->get("2fa/select", "controllers\auth@selectTwoFactorMethod");
    $router->post("2fa/select", "controllers\auth@chooseTwoFactorMethod");
    $router->get("2fa/verify", "controllers\auth@twoFactorChallenge");
    $router->post("2fa/verify", "controllers\auth@verifyTwoFactorChallenge");
    $router->post("2fa/email/resend", "controllers\auth@resendEmailCode");
    $router->get("uploads/{disk}/{filename}", "controllers\upload@show");
    $router->get("forgot-password", "controllers\auth@forgotPassword");
    $router->post("forgot-password", "controllers\auth@sendResetLink");
    $router->get("reset-password/{token}", "controllers\auth@resetPasswordForm");
    $router->post("reset-password/{token}", "controllers\auth@resetPassword");

    $router->get("dashboard", "controllers\dashboard@index");
    $router->get("admin", "controllers\admin@index");
    $router->post("admin/users/{id}/role", "controllers\admin@updateUserRole");
    $router->get("2fa/setup", "controllers\auth@twoFactorSettings");
    $router->post("2fa/email/enable", "controllers\auth@enableEmailTwoFactor");
    $router->post("2fa/email/disable", "controllers\auth@disableEmailTwoFactor");
    $router->post("2fa/totp/setup", "controllers\auth@prepareTotpSetup");
    $router->post("2fa/totp/confirm", "controllers\auth@confirmTotpSetup");
    $router->post("2fa/totp/disable", "controllers\auth@disableTotp");
    $router->post("uploads/file", "controllers\upload@uploadFile");
    $router->post("uploads/image", "controllers\upload@uploadImage");
    $router->post("dev/cache/clear", "controllers\auth@clearCache");
    $router->get("logout", "controllers\auth@logout");

    $router->get("register", "controllers\auth@register");
    $router->post("register", "controllers\auth@store");
};
