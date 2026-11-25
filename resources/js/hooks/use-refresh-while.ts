import { usePoll } from '@inertiajs/react';
import { useEffect } from 'react';

const REFRESH_INTERVAL_MS = 5000;

/**
 * Reloads the given props every few seconds while `active` is true, as a
 * safety net under their live updates.
 *
 * A broadcast can be lost even while the realtime connection looks fine, for
 * example when a deploy restarts Reverb or Horizon, or when the browser has
 * frozen a background tab. For a status someone is waiting on, such as a
 * running deployment, that would leave it stuck until a manual refresh.
 */
export function useRefreshWhile(only: string[], active: boolean) {
    const { start, stop } = usePoll(
        REFRESH_INTERVAL_MS,
        { only },
        { autoStart: false },
    );

    useEffect(() => {
        if (!active) {
            return;
        }

        start();

        return stop;
    }, [active, start, stop]);
}
