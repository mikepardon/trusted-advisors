import axios from 'axios';
import { isNativeApp, showInAppReview } from 'webtonative';

/**
 * Check with the server if we should prompt for app review,
 * and if so, trigger the native review dialog.
 */
export async function checkAndPromptReview(): Promise<void> {
    // Only prompt in native app context
    if (!isNativeApp) {
        return;
    }

    try {
        const response = await axios.get('/api/app-review/should-prompt');
        if (!response.data.should_prompt) {
            return;
        }

        // Trigger native review prompt via WTN SDK
        showInAppReview();

        // Mark as prompted on the server
        await axios.post('/api/app-review/prompted');
    } catch {
        // Swallow: review prompting is best-effort and must never disrupt the caller.
    }
}
