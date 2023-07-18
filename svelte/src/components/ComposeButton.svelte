<svelte:options immutable={true} />

<script lang="ts">
    import {
        callServices,
        type Course,
        type CreateMessageRequest,
        type ServiceError,
        type Strings,
    } from '../lib/services';
    import type { ViewParams } from '../lib/store';
    import { viewUrl } from '../lib/url';

    export let strings: Strings;
    export let courseid: number | undefined = undefined;
    export let courses: ReadonlyArray<Course>;
    export let onClick: ((params: ViewParams) => void) | undefined;
    export let onError: ((error: ServiceError) => void) | undefined;

    const handleClick = async (event: Event) => {
        const request: CreateMessageRequest = {
            methodname: 'create_message',
            courseid: courseid || courses[0].id,
        };

        let responses: unknown[];
        try {
            responses = await callServices([request]);
        } catch (error) {
            if (onError) {
                onError(error as ServiceError);
            } else {
                alert((error as ServiceError).message);
            }
            return;
        }
        const params: ViewParams = {
            tray: 'drafts',
            messageid: responses.pop() as number,
            courseid: request.courseid,
        };
        if (onClick) {
            event.preventDefault();
            onClick(params);
        } else {
            window.location.href = viewUrl(params);
        }
    };
</script>

<button
    type="button"
    class="btn btn-primary text-truncate px-2 px-sm-3"
    aria-label={strings.compose}
    on:click={handleClick}
>
    <i class="icon fa fa-edit mr-0 mr-sm-1" />
    <span class="d-none d-sm-inline">
        {strings.compose}
    </span>
</button>
