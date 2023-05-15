let modules = {};

/**
 * Loads an AMD modules with require().
 *
 * @param name Name of the module, e.g. "core/ajax".
 * @returns The module.
 */
export async function require(name: string): Promise<any> {
    if (modules[name] == null) {
        modules[name] = await new Promise((resolve) => {
            const require = window['require' + '']; // Hack to avoid type error.
            require([name], resolve);
        });
    }
    return modules[name];
}
