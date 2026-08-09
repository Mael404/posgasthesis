@extends('layouts.app')

@section('content')
    @php
        $announcementItems = $announcements->getCollection();
        $commentsByAnnouncement = $commentsByAnnouncement ?? [];
        $flattenComments = function ($comments) use (&$flattenComments) {
            return collect($comments)->flatMap(function ($comment) use (&$flattenComments) {
                return collect([$comment])->merge($flattenComments($comment['replies'] ?? []));
            })->values();
        };
        $announcementPayload = $announcementItems->map(function ($announcement) use ($commentsByAnnouncement, $flattenComments) {
            $adminName = $announcement->admin_name ?: 'Admin #' . $announcement->admin_id;
            $createdAt = $announcement->created_at
                ? \Carbon\Carbon::parse($announcement->created_at)->format('M d, Y h:i A')
                : 'N/A';
            $readAt = $announcement->read_at
                ? \Carbon\Carbon::parse($announcement->read_at)->format('M d, Y h:i A')
                : null;
            $comments = collect($commentsByAnnouncement[$announcement->id] ?? [])->values();
            $flatComments = $flattenComments($comments);

            return [
                'id' => (int) $announcement->id,
                'subject' => $announcement->subject,
                'message' => $announcement->message,
                'admin_name' => $adminName,
                'created_at' => $createdAt,
                'read_at' => $readAt,
                'is_read' => (bool) $readAt,
                'comments' => $comments->all(),
                'comment_count' => $flatComments->count(),
                'search' => strtolower($announcement->subject . ' ' . $announcement->message . ' ' . $adminName . ' ' . $flatComments->pluck('comment')->implode(' ') . ' ' . $flatComments->pluck('author_name')->implode(' ') . ' ' . $flatComments->pluck('staff_name')->implode(' ')),
            ];
        })->values();

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $textareaClass = 'min-h-28 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
    @endphp

    <x-common.page-breadcrumb pageTitle="Notifications" />

    <div
        x-data="staffAnnouncementsModule({
            announcements: @js($announcementPayload),
            unreadCount: @js((int) $unreadCount),
            csrfToken: @js(csrf_token()),
            markReadUrlTemplate: @js(route('staff.notifications.read', ['announcement' => '__ID__'])),
            commentUrlTemplate: @js(route('staff.notifications.comments.store', ['announcement' => '__ID__'])),
            initialAnnouncementId: @js((string) request('announcement', ''))
        })"
        x-init="init()"
        class="space-y-6">
        <style>[x-cloak] { display: none !important; }</style>

        <div
            x-show="toast.open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="fixed right-4 w-[calc(100%-2rem)] max-w-sm rounded-xl border border-white/10 p-4 text-white shadow-theme-lg sm:right-6"
            style="top: 5.5rem; z-index: 100300; background-color: #047857;"
            role="status"
            aria-live="polite">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15 text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6.75 12.25 3.25 3.25 7.25-7.25" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-theme-sm font-semibold text-white">Success</p>
                    <p class="mt-1 text-theme-sm text-white/90" x-text="toast.message"></p>
                </div>
                <button type="button" x-on:click="closeToast()" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="Close success message">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>
        </div>

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Notifications</h1>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:min-w-80">
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Total</p>
                        <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($announcements->total()) }}</p>
                    </article>
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Unread</p>
                        <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90" x-text="unreadCount"></p>
                    </article>
                </div>
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(240px,360px)] lg:items-end">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Inbox</h2>
                    <p class="mt-1 {{ $captionClass }}">Open a notice to mark it as read.</p>
                </div>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Search</span>
                    <input type="search" x-model.debounce.150ms="search" class="mt-1.5 {{ $inputClass }}" placeholder="Subject, message, or admin">
                </label>
            </div>

            <div class="space-y-3">
                @forelse ($announcementItems as $announcement)
                    @php
                        $adminName = $announcement->admin_name ?: 'Admin #' . $announcement->admin_id;
                        $createdAt = $announcement->created_at
                            ? \Carbon\Carbon::parse($announcement->created_at)->format('M d, Y h:i A')
                            : 'N/A';
                    @endphp
                    <article
                        x-show="matches('{{ $announcement->id }}')"
                        x-on:click="openDetails('{{ $announcement->id }}')"
                        x-on:keydown.enter.prevent="openDetails('{{ $announcement->id }}')"
                        x-on:keydown.space.prevent="openDetails('{{ $announcement->id }}')"
                        tabindex="0"
                        class="cursor-pointer rounded-xl border p-4 transition hover:border-brand-200 hover:bg-brand-50/30 hover:shadow-theme-xs dark:hover:border-brand-500/30 dark:hover:bg-brand-500/[0.05]"
                        :class="isRead('{{ $announcement->id }}')
                            ? 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.02]'
                            : 'border-brand-200 bg-brand-50/40 dark:border-brand-500/30 dark:bg-brand-500/[0.08]'">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="break-words text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $announcement->subject }}</h3>
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-theme-xs font-medium"
                                        :class="isRead('{{ $announcement->id }}')
                                            ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                            : 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'"
                                        x-text="isRead('{{ $announcement->id }}') ? 'Read' : 'Unread'"></span>
                                </div>
                                <p class="mt-2 line-clamp-3 whitespace-pre-line break-words text-theme-sm leading-6 text-gray-600 dark:text-gray-400">{{ $announcement->message }}</p>
                            </div>
                            <p class="shrink-0 text-theme-xs text-gray-500 dark:text-gray-400">{{ $createdAt }}</p>
                        </div>

                        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 space-y-2">
                                <p class="break-words text-theme-xs text-gray-500 dark:text-gray-400">
                                    Posted by <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $adminName }}</span>
                                </p>
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300" x-text="commentLabel('{{ $announcement->id }}')"></span>
                            </div>
                            <button type="button" x-on:click.stop="openDetails('{{ $announcement->id }}')" class="{{ $primaryButtonClass }}">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" stroke="currentColor" stroke-width="1.8"/></svg>
                                Open
                            </button>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                        <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12 20 4l-4 16-4-7-8-1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m12 13 4-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </div>
                        <p class="mt-3 text-theme-sm font-medium text-gray-800 dark:text-white/90">No notifications yet.</p>
                    </div>
                @endforelse
            </div>

            @if ($announcements->hasPages())
                <div class="mt-5">
                    {{ $announcements->links() }}
                </div>
            @endif
        </section>

        <div
            x-show="modal === 'details'"
            x-cloak
            x-on:keydown.escape.window="closeModal()"
            class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4"
            style="z-index: 100240;">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md" x-on:click="closeModal()"></div>
            <div class="relative max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <button type="button" x-on:click="closeModal()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400" aria-label="Close details">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>

                <div class="p-6 pr-14">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="break-words text-xl font-semibold text-gray-800 dark:text-white/90" x-text="selected ? selected.subject : 'Announcement'"></h2>
                        <span
                            x-show="selected"
                            x-cloak
                            class="inline-flex rounded-full px-2.5 py-0.5 text-theme-xs font-medium"
                            :class="selected && selected.is_read
                                ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                : 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'"
                            x-text="selected && selected.is_read ? 'Read' : 'Unread'"></span>
                    </div>
                    <p class="mt-2 break-words text-theme-sm text-gray-500 dark:text-gray-400" x-text="selected ? ('Posted by ' + selected.admin_name + ' | ' + selected.created_at) : ''"></p>
                    <p x-show="selected && selected.read_at" x-cloak class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-text="'Read on ' + selected.read_at"></p>
                    <div class="mt-5 max-h-[55vh] overflow-y-auto whitespace-pre-line break-words rounded-xl border border-gray-200 bg-gray-50 p-4 text-theme-sm leading-6 text-gray-700 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-300" x-text="selected ? selected.message : ''"></div>

                    <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Comment Thread</h3>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300" x-text="commentCountLabel(selected)"></span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <template x-if="selected && commentCount(selected) === 0">
                                <div class="rounded-xl border border-dashed border-gray-300 p-5 text-center text-theme-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    No comments yet.
                                </div>
                            </template>

                            <template x-for="comment in visibleThreadComments(selected)" :key="comment.id">
                                <div class="space-y-2" :style="threadIndentStyle(comment.depth)">
                                    <article
                                        class="rounded-xl border bg-white p-4 dark:bg-white/[0.02]"
                                        :class="comment.depth > 0 ? 'border-brand-100 dark:border-brand-500/20' : 'border-gray-200 dark:border-gray-800'">
                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="break-words text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="comment.author_name || comment.staff_name"></p>
                                                    <span
                                                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase"
                                                        :class="comment.author_label === 'Admin'
                                                            ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'
                                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
                                                        x-text="comment.author_label || 'Staff'"></span>
                                                </div>
                                            </div>
                                            <p class="shrink-0 text-theme-xs text-gray-500 dark:text-gray-400" x-text="comment.created_at"></p>
                                        </div>
                                        <p class="mt-2 whitespace-pre-line break-words text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="comment.comment"></p>
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <button
                                                type="button"
                                                x-show="comment.reply_count > 0"
                                                x-cloak
                                                x-on:click.stop="toggleThread(comment)"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-theme-xs font-semibold text-gray-600 transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 dark:border-gray-800 dark:text-gray-300 dark:hover:border-brand-500/30 dark:hover:bg-brand-500/10 dark:hover:text-brand-300">
                                                <svg class="size-3.5 transition-transform" :class="isThreadCollapsed(comment.id) ? '' : 'rotate-90'" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                    <path d="m7.5 4.5 5 5.5-5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span x-text="threadToggleLabel(comment)"></span>
                                            </button>
                                            <button type="button" x-on:click="startReply(comment)" class="text-theme-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                                                Reply
                                            </button>
                                        </div>
                                    </article>

                                    <form
                                        x-show="isReplyingTo(comment.id)"
                                        x-cloak
                                        x-on:submit.prevent="submitComment(comment.id)"
                                        class="rounded-xl border border-brand-100 bg-brand-50/40 p-4 dark:border-brand-500/20 dark:bg-brand-500/[0.06]">
                                        <p class="text-theme-xs font-medium text-gray-600 dark:text-gray-300">
                                            Replying to <span class="font-semibold text-gray-800 dark:text-white/90" x-text="comment.author_name || comment.staff_name"></span>
                                        </p>
                                        <textarea x-model.trim="replyForm.comment" class="mt-2 {{ $textareaClass }}" maxlength="2000" placeholder="Write a reply"></textarea>
                                        <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                            <button type="button" x-on:click="cancelReply()" class="{{ $secondaryButtonClass }}">Cancel</button>
                                            <button type="submit" class="{{ $primaryButtonClass }}" :disabled="isSavingReply(comment.id)">
                                                <span x-text="isSavingReply(comment.id) ? 'Sending...' : 'Send Reply'"></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </template>
                        </div>

                        <form x-on:submit.prevent="submitComment()" class="mt-5 space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                            <label class="block">
                                <span class="{{ $labelClass }}">Add Comment</span>
                                <textarea x-model.trim="commentForm.comment" class="mt-1.5 {{ $textareaClass }}" maxlength="2000" placeholder="Write a comment for the thread"></textarea>
                            </label>
                            <div class="flex justify-end">
                                <button type="submit" class="{{ $primaryButtonClass }}" :disabled="savingComment">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12 20 4l-4 16-4-7-8-1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m12 13 4-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    <span x-text="savingComment ? 'Sending...' : 'Send Comment'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div
            x-show="messageDialog.open"
            x-cloak
            x-on:keydown.escape.window="closeMessageDialog()"
            class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4"
            style="z-index: 100250;">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md" x-on:click="closeMessageDialog()"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8.25h.01M11.25 11.25h1.5v5h-1.5z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Unable to Continue</h2>
                            <p class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="messageDialog.message"></p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" x-on:click="closeMessageDialog()" class="{{ $primaryButtonClass }}">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function staffAnnouncementsModule(config) {
            return {
                announcements: config.announcements || [],
                announcementsById: {},
                unreadCount: Number(config.unreadCount || 0),
                search: '',
                modal: null,
                selected: null,
                markingRead: {},
                commentForm: { comment: '' },
                replyForm: { parentId: null, comment: '' },
                savingComment: false,
                savingReplies: {},
                collapsedThreads: {},
                toast: { open: false, message: '', timer: null },
                messageDialog: { open: false, message: '' },

                init() {
                    this.announcementsById = Object.fromEntries(this.announcements.map(item => [String(item.id), item]));

                    if (config.initialAnnouncementId) {
                        this.$nextTick(() => this.openDetails(config.initialAnnouncementId));
                    }
                },

                matches(id) {
                    const item = this.announcementsById[String(id)];
                    const value = String(this.search || '').toLowerCase().trim();
                    return !value || (item && item.search.includes(value));
                },

                isRead(id) {
                    const item = this.announcementsById[String(id)];
                    return !!(item && item.is_read);
                },

                commentLabel(id) {
                    const item = this.announcementsById[String(id)];
                    const count = item ? this.commentCount(item) : 0;
                    return count.toLocaleString() + ' ' + (count === 1 ? 'comment' : 'comments');
                },

                openDetails(id) {
                    const item = this.announcementsById[String(id)];
                    if (!item) return;

                    this.selected = item;
                    this.modal = 'details';

                    if (!item.is_read) {
                        this.markRead(id);
                    }
                },

                closeModal() {
                    if (this.messageDialog.open) return;
                    this.modal = null;
                    this.selected = null;
                    this.commentForm.comment = '';
                    this.collapsedThreads = {};
                    this.cancelReply();
                },

                async markRead(id) {
                    const item = this.announcementsById[String(id)];
                    if (!item || item.is_read || this.markingRead[String(id)]) return;

                    this.markingRead[String(id)] = true;

                    try {
                        const response = await fetch(this.markReadUrl(id), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            }
                        });
                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            this.showMessage(data.message || 'Unable to mark announcement as read.');
                            return;
                        }

                        item.is_read = true;
                        item.read_at = data.read_at || item.read_at;
                        this.unreadCount = Number(data.unread_count || 0);

                        if (this.selected && String(this.selected.id) === String(id)) {
                            this.selected = item;
                        }
                    } catch (error) {
                        this.showMessage('An unexpected error occurred.');
                    } finally {
                        delete this.markingRead[String(id)];
                    }
                },

                markReadUrl(id) {
                    return config.markReadUrlTemplate.replace('__ID__', encodeURIComponent(id));
                },

                async submitComment(parentId = null) {
                    if (!this.selected) return;

                    const isReply = !!parentId;
                    const source = isReply ? this.replyForm : this.commentForm;
                    const comment = String(source.comment || '').trim();

                    if (!comment) {
                        this.showMessage(isReply ? 'Please write a reply before sending.' : 'Please write a comment before sending.');
                        return;
                    }

                    if (isReply) {
                        this.setReplySaving(parentId, true);
                    } else {
                        this.savingComment = true;
                    }

                    try {
                        const response = await fetch(this.commentUrl(this.selected.id), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                comment,
                                parent_id: isReply ? Number(parentId) : null
                            })
                        });
                        const data = await response.json();

                        if (!response.ok || !data.success || !data.comment) {
                            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                            this.showMessage(data.message || firstError || 'Unable to send comment.');
                            return;
                        }

                        const item = this.announcementsById[String(this.selected.id)];

                        if (item) {
                            this.addCommentToItem(item, data.comment);
                            this.selected = item;
                        }

                        if (isReply) {
                            this.cancelReply();
                        } else {
                            this.commentForm.comment = '';
                        }

                        this.showToast(data.message || (isReply ? 'Reply sent to admin.' : 'Comment sent to admin.'));
                    } catch (error) {
                        this.showMessage('An unexpected error occurred.');
                    } finally {
                        if (isReply) {
                            this.setReplySaving(parentId, false);
                        } else {
                            this.savingComment = false;
                        }
                    }
                },

                commentUrl(id) {
                    return config.commentUrlTemplate.replace('__ID__', encodeURIComponent(id));
                },

                startReply(comment) {
                    if (!comment || !comment.id) return;
                    this.replyForm = { parentId: comment.id, comment: '' };
                },

                cancelReply() {
                    this.replyForm = { parentId: null, comment: '' };
                },

                isReplyingTo(id) {
                    return String(this.replyForm.parentId || '') === String(id);
                },

                isSavingReply(id) {
                    return !!this.savingReplies[String(id)];
                },

                setReplySaving(id, value) {
                    const key = String(id);
                    const next = { ...this.savingReplies };

                    if (value) {
                        next[key] = true;
                    } else {
                        delete next[key];
                    }

                    this.savingReplies = next;
                },

                addCommentToItem(item, comment) {
                    const normalized = this.normalizeComment(comment);
                    item.comments = Array.isArray(item.comments) ? item.comments : [];

                    if (normalized.parent_id) {
                        const parent = this.findComment(item.comments, normalized.parent_id);

                        if (parent) {
                            parent.replies = Array.isArray(parent.replies) ? parent.replies : [];
                            parent.replies.push(normalized);
                            this.expandThread(parent.id);
                        } else {
                            item.comments.push(normalized);
                        }
                    } else {
                        item.comments.push(normalized);
                    }

                    item.comment_count = this.commentCount(item);
                    item.search = [
                        item.search || '',
                        normalized.comment || '',
                        normalized.author_name || normalized.staff_name || ''
                    ].join(' ').toLowerCase();
                },

                normalizeComment(comment) {
                    return {
                        ...comment,
                        replies: Array.isArray(comment.replies) ? comment.replies : []
                    };
                },

                findComment(comments, id) {
                    const targetId = String(id);

                    for (const comment of Array.isArray(comments) ? comments : []) {
                        if (String(comment.id) === targetId) {
                            return comment;
                        }

                        const found = this.findComment(comment.replies || [], id);

                        if (found) {
                            return found;
                        }
                    }

                    return null;
                },

                visibleThreadComments(item) {
                    const output = [];

                    const walk = (comments, depth = 0) => {
                        (Array.isArray(comments) ? comments : []).forEach((comment) => {
                            const replyCount = this.commentListCount(comment.replies || []);

                            output.push({
                                ...comment,
                                depth,
                                reply_count: replyCount
                            });

                            if (replyCount > 0 && !this.isThreadCollapsed(comment.id)) {
                                walk(comment.replies || [], depth + 1);
                            }
                        });
                    };

                    walk(item ? item.comments : []);

                    return output;
                },

                commentCount(item) {
                    return this.commentListCount(item ? item.comments : []);
                },

                commentListCount(comments) {
                    return (Array.isArray(comments) ? comments : []).reduce((total, comment) => {
                        return total + 1 + this.commentListCount(comment.replies || []);
                    }, 0);
                },

                commentCountLabel(item) {
                    const count = this.commentCount(item);
                    return count.toLocaleString() + ' ' + (count === 1 ? 'comment' : 'comments');
                },

                toggleThread(comment) {
                    if (!comment || !comment.id || Number(comment.reply_count || 0) < 1) return;

                    const key = String(comment.id);
                    const next = { ...this.collapsedThreads };

                    if (next[key]) {
                        delete next[key];
                    } else {
                        next[key] = true;
                    }

                    this.collapsedThreads = next;
                },

                expandThread(id) {
                    const key = String(id || '');
                    if (!key || !this.collapsedThreads[key]) return;

                    const next = { ...this.collapsedThreads };
                    delete next[key];
                    this.collapsedThreads = next;
                },

                isThreadCollapsed(id) {
                    return !!this.collapsedThreads[String(id)];
                },

                threadToggleLabel(comment) {
                    const count = Number(comment.reply_count || 0);
                    const action = this.isThreadCollapsed(comment.id) ? 'Show' : 'Hide';

                    return action + ' ' + count.toLocaleString() + ' ' + (count === 1 ? 'reply' : 'replies');
                },

                threadIndentStyle(depth) {
                    const indentation = Math.min(Number(depth || 0), 5) * 18;
                    return indentation > 0 ? `margin-left: ${indentation}px;` : '';
                },

                showToast(message) {
                    this.closeToast();
                    this.toast.message = message;
                    this.toast.open = true;
                    this.toast.timer = window.setTimeout(() => this.closeToast(), 3000);
                },

                closeToast() {
                    if (this.toast.timer) {
                        window.clearTimeout(this.toast.timer);
                    }
                    this.toast = { open: false, message: '', timer: null };
                },

                showMessage(message) {
                    this.messageDialog = { open: true, message };
                },

                closeMessageDialog() {
                    this.messageDialog.open = false;
                }
            };
        }
    </script>
@endpush
