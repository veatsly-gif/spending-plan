<?php

declare(strict_types=1);

namespace App\Redis;

enum RedisDataKey: string
{
    case INCOME_RATES_LIVE = 'income_rates_live';
    case SPENDING_PLAN_SUGGESTIONS = 'spending_plan_suggestions';
    case NOTIFICATION_ACTION_STATE = 'notification_action_state';
    case TELEGRAM_CONVERSATION_STATE = 'telegram_conversation_state';
    case ADMIN_DAILY_POPUP_QUEUE = 'admin_daily_popup_queue';
    case NOTIFICATION_TRIGGER_COUNT = 'notification_trigger_count';
    case NOTIFICATION_TRIGGER_LAST = 'notification_trigger_last';
    case MONTHLY_BALANCE_SNAPSHOT = 'monthly_balance_snapshot';
}
