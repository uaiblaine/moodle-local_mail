import { require } from './amd';

export interface Date {
    readonly timestamp: number;
    readonly format: string;
    readonly fixday?: boolean;
    readonly fixhour?: boolean;
};

/**
 * Formats a list of dates.
 * 
 * A web service call is needed to format dates, but results are cached in local storage.
 *
 * @param strings List of language string keys, components and params.
 * @returns List of formatted dates.
 */
export async function formatDates(dates: Date[]): Promise<string[]> {
    let user_date = await require('core/user_date');
    try {
        return await user_date.get_dates(dates);
    } catch (error) {
        let notification = await require('core/notification');
        notification.exception(error);
        throw error;
    }
}
