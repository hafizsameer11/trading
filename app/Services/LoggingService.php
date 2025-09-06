<?php

namespace App\Services;

use App\Models\SystemLog;
use App\Models\User;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Notification;
use App\Models\PaymentMethod;
use App\Models\Pair;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class LoggingService
{
    /**
     * Log a system action
     */
    public static function log(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldData = null,
        ?array $newData = null,
        string $level = 'info',
        ?array $meta = null,
        ?Request $request = null
    ): SystemLog {
        $logData = [
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'old_data' => $oldData,
            'new_data' => $newData,
            'level' => $level,
            'meta' => $meta,
        ];

        if ($model) {
            $logData['model_type'] = get_class($model);
            $logData['model_id'] = $model->id;
        }

        if ($request) {
            $logData['ip_address'] = $request->ip();
            $logData['user_agent'] = $request->userAgent();
        }

        return SystemLog::create($logData);
    }

    /**
     * Log user creation
     */
    public static function logUserCreated(User $user, ?Request $request = null): SystemLog
    {
        return self::log(
            'user_created',
            "User '{$user->name}' ({$user->email}) was created",
            $user,
            null,
            $user->toArray(),
            'info',
            ['email' => $user->email],
            $request
        );
    }

    /**
     * Log user update
     */
    public static function logUserUpdated(User $user, array $oldData, ?Request $request = null): SystemLog
    {
        return self::log(
            'user_updated',
            "User '{$user->name}' was updated",
            $user,
            $oldData,
            $user->toArray(),
            'info',
            null,
            $request
        );
    }

    /**
     * Log deposit approval
     */
    public static function logDepositApproved(Deposit $deposit, ?Request $request = null): SystemLog
    {
        return self::log(
            'deposit_approved',
            "Deposit #{$deposit->id} of {$deposit->amount} was approved for user '{$deposit->user->name}'",
            $deposit,
            ['status' => 'PENDING'],
            ['status' => 'APPROVED'],
            'info',
            ['amount' => $deposit->amount, 'user_id' => $deposit->user_id],
            $request
        );
    }

    /**
     * Log deposit rejection
     */
    public static function logDepositRejected(Deposit $deposit, ?Request $request = null): SystemLog
    {
        return self::log(
            'deposit_rejected',
            "Deposit #{$deposit->id} of {$deposit->amount} was rejected for user '{$deposit->user->name}'",
            $deposit,
            ['status' => 'PENDING'],
            ['status' => 'REJECTED'],
            'warning',
            ['amount' => $deposit->amount, 'user_id' => $deposit->user_id],
            $request
        );
    }

    /**
     * Log withdrawal approval
     */
    public static function logWithdrawalApproved(Withdrawal $withdrawal, ?Request $request = null): SystemLog
    {
        return self::log(
            'withdrawal_approved',
            "Withdrawal #{$withdrawal->id} of {$withdrawal->amount} was approved for user '{$withdrawal->user->name}'",
            $withdrawal,
            ['status' => 'PENDING'],
            ['status' => 'APPROVED'],
            'info',
            ['amount' => $withdrawal->amount, 'user_id' => $withdrawal->user_id],
            $request
        );
    }

    /**
     * Log withdrawal rejection
     */
    public static function logWithdrawalRejected(Withdrawal $withdrawal, ?Request $request = null): SystemLog
    {
        return self::log(
            'withdrawal_rejected',
            "Withdrawal #{$withdrawal->id} of {$withdrawal->amount} was rejected for user '{$withdrawal->user->name}'",
            $withdrawal,
            ['status' => 'PENDING'],
            ['status' => 'REJECTED'],
            'warning',
            ['amount' => $withdrawal->amount, 'user_id' => $withdrawal->user_id],
            $request
        );
    }

    /**
     * Log system settings update
     */
    public static function logSystemSettingsUpdated(array $oldData, array $newData, ?Request $request = null): SystemLog
    {
        return self::log(
            'system_settings_updated',
            'System settings were updated',
            null,
            $oldData,
            $newData,
            'info',
            null,
            $request
        );
    }

    /**
     * Log notification creation
     */
    public static function logNotificationCreated(Notification $notification, ?Request $request = null): SystemLog
    {
        $description = $notification->is_global 
            ? "Global notification '{$notification->title}' was created"
            : "Notification '{$notification->title}' was sent to user '{$notification->user->name}'";

        return self::log(
            'notification_created',
            $description,
            $notification,
            null,
            $notification->toArray(),
            'info',
            ['is_global' => $notification->is_global, 'priority' => $notification->priority],
            $request
        );
    }

    /**
     * Log payment method creation
     */
    public static function logPaymentMethodCreated(PaymentMethod $paymentMethod, ?Request $request = null): SystemLog
    {
        return self::log(
            'payment_method_created',
            "Payment method '{$paymentMethod->name}' was created",
            $paymentMethod,
            null,
            $paymentMethod->toArray(),
            'info',
            ['type' => $paymentMethod->type],
            $request
        );
    }

    /**
     * Log payment method update
     */
    public static function logPaymentMethodUpdated(PaymentMethod $paymentMethod, array $oldData, ?Request $request = null): SystemLog
    {
        return self::log(
            'payment_method_updated',
            "Payment method '{$paymentMethod->name}' was updated",
            $paymentMethod,
            $oldData,
            $paymentMethod->toArray(),
            'info',
            ['type' => $paymentMethod->type],
            $request
        );
    }

    /**
     * Log pair creation
     */
    public static function logPairCreated(Pair $pair, ?Request $request = null): SystemLog
    {
        return self::log(
            'pair_created',
            "Trading pair '{$pair->symbol}' was created",
            $pair,
            null,
            $pair->toArray(),
            'info',
            ['symbol' => $pair->symbol, 'type' => $pair->type],
            $request
        );
    }

    /**
     * Log pair update
     */
    public static function logPairUpdated(Pair $pair, array $oldData, ?Request $request = null): SystemLog
    {
        return self::log(
            'pair_updated',
            "Trading pair '{$pair->symbol}' was updated",
            $pair,
            $oldData,
            $pair->toArray(),
            'info',
            ['symbol' => $pair->symbol, 'type' => $pair->type],
            $request
        );
    }

    /**
     * Log admin login
     */
    public static function logAdminLogin(User $user, ?Request $request = null): SystemLog
    {
        return self::log(
            'admin_login',
            "Admin '{$user->name}' logged in",
            $user,
            null,
            null,
            'info',
            ['email' => $user->email],
            $request
        );
    }

    /**
     * Log admin logout
     */
    public static function logAdminLogout(User $user, ?Request $request = null): SystemLog
    {
        return self::log(
            'admin_logout',
            "Admin '{$user->name}' logged out",
            $user,
            null,
            null,
            'info',
            ['email' => $user->email],
            $request
        );
    }

    /**
     * Log error
     */
    public static function logError(string $description, ?array $meta = null, ?Request $request = null): SystemLog
    {
        return self::log(
            'system_error',
            $description,
            null,
            null,
            null,
            'error',
            $meta,
            $request
        );
    }

    /**
     * Log critical error
     */
    public static function logCritical(string $description, ?array $meta = null, ?Request $request = null): SystemLog
    {
        return self::log(
            'system_critical',
            $description,
            null,
            null,
            null,
            'critical',
            $meta,
            $request
        );
    }
}
