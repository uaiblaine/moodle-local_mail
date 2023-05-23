/**
 * Replaces {$a} parameters of a language string.
 *
 * @param string Language string.
 * @param param A string, a number or an object to replace parameters with.
 * @returns String with parameters replaced.
 */
export function replaceStringParams(
    string: string,
    params: string | number | Record<string, string | number>,
): string {
    if (typeof params == 'string' || typeof params == 'number') {
        string = string.replace('{$a}', params.toString());
    } else {
        for (const key in params) {
            string = string.replace(`{$a->${key}}`, params[key].toString());
        }
    }
    return string;
}

/**
 * Waits for the number of specified miliseconds before continuing.
 *
 * @param miliseconds Number of miliseconds to wait for.
 * @returns Promise that is resolved after the specified miliseconds.
 */
export async function sleep(miliseconds: number): Promise<void> {
    return new Promise((resolve) => {
        setTimeout(resolve, miliseconds);
    });
}
