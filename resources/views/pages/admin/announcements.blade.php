@extends('layouts.app')

@section('content')
    @php
        $announcementItems = $announcements->getCollection();
        $commentsByAnnouncement = $commentsByAnnouncement ?? [];
        $totalComments = (int) ($totalComments ?? 0);
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
            $updatedAt = $announcement->updated_at
                ? \Carbon\Carbon::parse($announcement->updated_at)->format('M d, Y h:i A')
                : $createdAt;
            $comments = collect($commentsByAnnouncement[$announcement->id] ?? [])->values();
            $flatComments = $flattenComments($comments);
            $latestComment = $flatComments->last();

            return [
                'id' => (int) $announcement->id,
                'subject' => $announcement->subject,
                'message' => $announcement->message,
                'admin_name' => $adminName,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'comments' => $comments->all(),
                'comment_count' => $flatComments->count(),
                'latest_commenter' => $latestComment['author_name'] ?? ($latestComment['staff_name'] ?? null),
                'search' => strtolower($announcement->subject . ' ' . $announcement->message . ' ' . $adminName . ' ' . $flatComments->pluck('comment')->implode(' ') . ' ' . $flatComments->pluck('author_name')->implode(' ') . ' ' . $flatComments->pluck('staff_name')->implode(' ')),
            ];
        })->values();

        $latestAnnouncement = $announcementItems->first();
        $latestPostedAt = $latestAnnouncement && $latestAnnouncement->created_at
            ? \Carbon\Carbon::parse($latestAnnouncement->created_at)->format('M d, Y h:i A')
            : 'None yet';

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $textareaClass = 'min-h-36 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $viewIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15';
        $editIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-warning-200 bg-warning-50 text-warning-700 shadow-theme-xs transition hover:border-warning-300 hover:bg-warning-100 dark:border-warning-500/25 dark:bg-warning-500/10 dark:text-warning-400 dark:hover:bg-warning-500/15';
        $deleteIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-error-200 bg-error-50 text-error-600 shadow-theme-xs transition hover:border-error-300 hover:bg-error-100 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400 dark:hover:bg-error-500/15';
    @endphp

    <x-common.page-breadcrumb pageTitle="Announcements" />

    <div
        x-data="announcementAdminModule({
            announcements: @js($announcementPayload),
            csrfToken: @js(csrf_token()),
            storeUrl: @js(route('admin.announcements.store')),
            updateUrlTemplate: @js(route('admin.announcements.update', ['announcement' => '__ID__'])),
            deleteUrlTemplate: @js(route('admin.announcements.destroy', ['announcement' => '__ID__'])),
            commentUrlTemplate: @js(route('admin.announcements.comments.store', ['announcement' => '__ID__'])),
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

        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 text-theme-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-500">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Announcements</h1>
                    <p class="mt-2 max-w-3xl {{ $captionClass }}">Post station updates for staff accounts and keep old notices editable from one place.</p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:min-w-[28rem] sm:grid-cols-3">
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Total Posted</p>
                        <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($announcements->total()) }}</p>
                    </article>
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Latest</p>
                        <p class="mt-2 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $latestPostedAt }}</p>
                    </article>
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Staff Comments</p>
                        <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($totalComments) }}</p>
                    </article>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(320px,420px)_minmax(0,1fr)]">
            <section class="{{ $cardClass }}">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">New Announcement</h2>
                    <p class="mt-1 {{ $captionClass }}">Staff will see the notice in their announcements inbox.</p>
                </div>

                <form x-on:submit.prevent="submitCreate()" class="mt-5 space-y-4">
                    <label class="block">
                        <span class="{{ $labelClass }}">Posted By</span>
                        <input type="text" class="mt-1.5 {{ $inputClass }}" value="{{ auth()->user()->name }}" disabled>
                    </label>

                    <label class="block">
                        <span class="{{ $labelClass }}">Subject</span>
                        <input type="text" x-model.trim="createForm.subject" class="mt-1.5 {{ $inputClass }}" maxlength="150" placeholder="Short announcement subject">
                    </label>

                    <label class="block">
                        <span class="{{ $labelClass }}">Message</span>
                        <textarea x-model.trim="createForm.message" class="mt-1.5 {{ $textareaClass }}" maxlength="5000" placeholder="Write the full announcement"></textarea>
                    </label>

                    <button type="submit" class="{{ $primaryButtonClass }} w-full" :disabled="savingCreate">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12 20 4l-4 16-4-7-8-1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m12 13 4-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        <span x-text="savingCreate ? 'Posting...' : 'Post Announcement'"></span>
                    </button>
                </form>
            </section>

            <section class="{{ $cardClass }}">
                <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(240px,340px)] lg:items-end">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Posted Announcements</h2>
                        <p class="mt-1 {{ $captionClass }}">Newest announcements appear first. Staff comments are shown in each detail view.</p>
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
                            $commentCount = $flattenComments($commentsByAnnouncement[$announcement->id] ?? [])->count();
                        @endphp
                        <article
                            id="announcement-{{ $announcement->id }}"
                            x-show="matches('{{ $announcement->id }}')"
                            x-on:click="openDetails('{{ $announcement->id }}')"
                            x-on:keydown.enter.prevent="openDetails('{{ $announcement->id }}')"
                            x-on:keydown.space.prevent="openDetails('{{ $announcement->id }}')"
                            tabindex="0"
                            class="cursor-pointer rounded-xl border border-gray-200 bg-white p-4 transition hover:border-brand-200 hover:bg-brand-50/30 hover:shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-500/30 dark:hover:bg-brand-500/[0.05]">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <h3 class="break-words text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $announcement->subject }}</h3>
                                    <p class="mt-2 line-clamp-3 whitespace-pre-line break-words text-theme-sm leading-6 text-gray-600 dark:text-gray-400">{{ $announcement->message }}</p>
                                </div>
                                <p class="shrink-0 text-theme-xs text-gray-500 dark:text-gray-400">{{ $createdAt }}</p>
                            </div>

                            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0 space-y-2">
                                    <p class="break-words text-theme-xs text-gray-500 dark:text-gray-400">
                                        Posted by <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $adminName }}</span>
                                    </p>
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                        {{ number_format($commentCount) }} {{ \Illuminate\Support\Str::plural('comment', $commentCount) }}
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center gap-2" x-on:click.stop="false">
                                    <button type="button" x-on:click="openDetails('{{ $announcement->id }}')" class="{{ $viewIconButtonClass }}" title="View announcement" aria-label="View announcement">
                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" stroke="currentColor" stroke-width="1.8"/></svg>
                                    </button>
                                    <button type="button" x-on:click="openEdit('{{ $announcement->id }}')" class="{{ $editIconButtonClass }}" title="Edit announcement" aria-label="Edit announcement">
                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m14.25 5.25 4.5 4.5M5 19l4.25-.75L19.25 8.25a3.18 3.18 0 0 0-4.5-4.5L4.75 13.75 4 18.25c-.05.45.55.8 1 .75Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" x-on:click="requestDelete('{{ $announcement->id }}')" class="{{ $deleteIconButtonClass }}" title="Delete announcement" aria-label="Delete announcement">
                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5.25 7.25h13.5M9.75 10.75v5.5M14.25 10.75v5.5M8.25 7.25l.5-2h6.5l.5 2M7.25 7.25l.75 12h8l.75-12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                            <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12 20 4l-4 16-4-7-8-1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m12 13 4-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            </div>
                            <p class="mt-3 text-theme-sm font-medium text-gray-800 dark:text-white/90">No announcements yet.</p>
                        </div>
                    @endforelse
                </div>

                @if ($announcements->hasPages())
                    <div class="mt-5">
                        {{ $announcements->links() }}
                    </div>
                @endif
            </section>
        </div>

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
                    <h2 class="break-words text-xl font-semibold text-gray-800 dark:text-white/90" x-text="selected ? selected.subject : 'Announcement'"></h2>
                    <p class="mt-2 break-words text-theme-sm text-gray-500 dark:text-gray-400" x-text="selected ? ('Posted by ' + selected.admin_name + ' | ' + selected.created_at) : ''"></p>
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
                                <textarea x-model.trim="commentForm.comment" class="mt-1.5 {{ $textareaClass }}" maxlength="2000" placeholder="Write a comment or update for the thread"></textarea>
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
            x-show="modal === 'edit'"
            x-cloak
            x-on:keydown.escape.window="closeModal()"
            class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4"
            style="z-index: 100240;">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md" x-on:click="closeModal()"></div>
            <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 p-6 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Edit Announcement</h2>
                    <p class="mt-1 {{ $captionClass }}">Changes apply to the staff inbox immediately after saving.</p>
                </div>

                <form x-on:submit.prevent="submitUpdate()" class="space-y-4 p-6">
                    <label class="block">
                        <span class="{{ $labelClass }}">Subject</span>
                        <input type="text" x-model.trim="editForm.subject" class="mt-1.5 {{ $inputClass }}" maxlength="150">
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Message</span>
                        <textarea x-model.trim="editForm.message" class="mt-1.5 {{ $textareaClass }}" maxlength="5000"></textarea>
                    </label>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="closeModal()" class="{{ $secondaryButtonClass }}">Cancel</button>
                        <button type="submit" class="{{ $primaryButtonClass }}" :disabled="savingEdit">
                            <span x-text="savingEdit ? 'Saving...' : 'Save Changes'"></span>
                        </button>
                    </div>
                </form>
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
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="messageDialog.title"></h2>
                            <p class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="messageDialog.message"></p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" x-on:click="closeMessageDialog()" class="{{ $primaryButtonClass }}">OK</button>
                    </div>
                </div>
            </div>
        </div>

        <div
            x-show="confirmation.open"
            x-cloak
            x-on:keydown.escape.window="resolveConfirmation(false)"
            class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4"
            style="z-index: 100260;">
            <div class="fixed inset-0 bg-gray-950/85 backdrop-blur-md" x-on:click="resolveConfirmation(false)"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                            :class="confirmation.tone === 'danger'
                                ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                                : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8.5v4.25M12 16h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="confirmation.title"></h2>
                            <p class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="confirmation.message"></p>
                            <p x-show="confirmation.details" x-cloak class="mt-2 break-words text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="confirmation.details"></p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="resolveConfirmation(false)" class="{{ $secondaryButtonClass }}" x-text="confirmation.cancelText"></button>
                        <button
                            type="button"
                            x-ref="confirmPrimary"
                            x-on:click="resolveConfirmation(true)"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition"
                            :class="confirmation.tone === 'danger' ? 'bg-error-500 hover:bg-error-600' : 'bg-brand-500 hover:bg-brand-600'"
                            x-text="confirmation.confirmText"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function announcementAdminModule(config) {
            return {
                announcements: config.announcements || [],
                announcementsById: {},
                search: '',
                modal: null,
                selected: null,
                createForm: { subject: '', message: '' },
                editForm: { id: '', subject: '', message: '' },
                commentForm: { comment: '' },
                replyForm: { parentId: null, comment: '' },
                savingCreate: false,
                savingEdit: false,
                savingComment: false,
                savingReplies: {},
                collapsedThreads: {},
                toast: { open: false, message: '', timer: null },
                messageDialog: { open: false, title: '', message: '' },
                confirmation: {
                    open: false,
                    title: '',
                    message: '',
                    details: '',
                    confirmText: 'Confirm',
                    cancelText: 'Cancel',
                    tone: 'brand',
                    resolver: null
                },
                reloadTimer: null,
                feedbackClosing: false,

                init() {
                    this.announcementsById = Object.fromEntries(this.announcements.map(item => [String(item.id), item]));
                    this.showStoredToast();

                    if (config.initialAnnouncementId) {
                        this.$nextTick(() => this.openDetails(config.initialAnnouncementId));
                    }
                },

                matches(id) {
                    const item = this.announcementsById[String(id)];
                    const value = String(this.search || '').toLowerCase().trim();
                    return !value || (item && item.search.includes(value));
                },

                openDetails(id) {
                    const item = this.announcementsById[String(id)];
                    if (!item) return;
                    this.cancelReply();
                    this.selected = item;
                    this.modal = 'details';
                },

                openEdit(id) {
                    const item = this.announcementsById[String(id)];
                    if (!item) return;
                    this.selected = item;
                    this.editForm = {
                        id: item.id,
                        subject: item.subject || '',
                        message: item.message || ''
                    };
                    this.modal = 'edit';
                },

                closeModal() {
                    if (this.confirmation.open || this.messageDialog.open || this.feedbackClosing) return;
                    this.modal = null;
                    this.selected = null;
                    this.commentForm.comment = '';
                    this.collapsedThreads = {};
                    this.cancelReply();
                },

                async submitCreate() {
                    if (!this.createForm.subject || !this.createForm.message) {
                        this.showMessage('Subject and message are required.');
                        return;
                    }

                    const confirmed = await this.askConfirmation({
                        title: 'Post Announcement',
                        message: 'Post this announcement for all staff?',
                        confirmText: 'Post Announcement',
                        tone: 'brand'
                    });
                    if (!confirmed) return;

                    this.savingCreate = true;
                    const data = await this.sendJson(config.storeUrl, 'POST', this.createForm);
                    this.savingCreate = false;

                    if (!data.success) {
                        this.showMessage(data.message || 'Unable to post announcement.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Announcement posted.');
                },

                async submitUpdate() {
                    if (!this.editForm.id || !this.editForm.subject || !this.editForm.message) {
                        this.showMessage('Subject and message are required.');
                        return;
                    }

                    const confirmed = await this.askConfirmation({
                        title: 'Update Announcement',
                        message: 'Save changes to this announcement?',
                        confirmText: 'Save Changes',
                        tone: 'brand'
                    });
                    if (!confirmed) return;

                    this.savingEdit = true;
                    const data = await this.sendJson(this.updateUrl(this.editForm.id), 'PUT', this.editForm);
                    this.savingEdit = false;

                    if (!data.success) {
                        this.showMessage(data.message || 'Unable to update announcement.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Announcement updated.');
                },

                async requestDelete(id) {
                    const item = this.announcementsById[String(id)];
                    if (!item) return;

                    const confirmed = await this.askConfirmation({
                        title: 'Delete Announcement',
                        message: 'Delete this announcement?',
                        details: item.subject || '',
                        confirmText: 'Delete',
                        tone: 'danger'
                    });
                    if (!confirmed) return;

                    const data = await this.sendJson(this.deleteUrl(id), 'DELETE');

                    if (!data.success) {
                        this.showMessage(data.message || 'Unable to delete announcement.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Announcement deleted.');
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

                    const data = await this.sendJson(this.commentUrl(this.selected.id), 'POST', {
                        comment,
                        parent_id: isReply ? Number(parentId) : null
                    });

                    if (isReply) {
                        this.setReplySaving(parentId, false);
                    } else {
                        this.savingComment = false;
                    }

                    if (!data.success || !data.comment) {
                        this.showMessage(data.message || 'Unable to send comment.');
                        return;
                    }

                    this.addCommentToSelected(data.comment);

                    if (isReply) {
                        this.cancelReply();
                    } else {
                        this.commentForm.comment = '';
                    }

                    this.showToast(data.message || (isReply ? 'Reply posted.' : 'Comment posted.'));
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

                addCommentToSelected(comment) {
                    const item = this.selected ? this.announcementsById[String(this.selected.id)] : null;
                    if (!item) return;

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
                    this.selected = item;
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

                updateUrl(id) {
                    return config.updateUrlTemplate.replace('__ID__', encodeURIComponent(id));
                },

                deleteUrl(id) {
                    return config.deleteUrlTemplate.replace('__ID__', encodeURIComponent(id));
                },

                commentUrl(id) {
                    return config.commentUrlTemplate.replace('__ID__', encodeURIComponent(id));
                },

                async sendJson(url, method, payload = null) {
                    try {
                        const options = {
                            method,
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            }
                        };

                        if (payload !== null) {
                            options.body = JSON.stringify(payload);
                        }

                        const response = await fetch(url, options);
                        const data = await response.json();

                        if (response.ok) {
                            return data;
                        }

                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                        return { success: false, message: data.message || firstError || 'Request failed.' };
                    } catch (error) {
                        return { success: false, message: 'An unexpected error occurred.' };
                    }
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

                showStoredToast() {
                    let message = '';

                    try {
                        message = window.sessionStorage.getItem('announcement-admin-toast') || '';
                        window.sessionStorage.removeItem('announcement-admin-toast');
                    } catch (error) {
                        message = '';
                    }

                    if (message) {
                        this.$nextTick(() => this.showToast(message));
                    }
                },

                showMessage(message, title = 'Unable to Continue') {
                    this.messageDialog = { open: true, title, message };
                },

                closeMessageDialog() {
                    this.feedbackClosing = true;
                    this.messageDialog.open = false;

                    this.$nextTick(() => {
                        this.feedbackClosing = false;
                    });
                },

                askConfirmation(options = {}) {
                    return new Promise(resolve => {
                        this.confirmation = {
                            open: true,
                            title: options.title || 'Confirm Action',
                            message: options.message || 'Please confirm this action.',
                            details: options.details || '',
                            confirmText: options.confirmText || 'Confirm',
                            cancelText: options.cancelText || 'Cancel',
                            tone: options.tone || 'brand',
                            resolver: resolve
                        };

                        this.$nextTick(() => {
                            if (this.$refs.confirmPrimary) {
                                this.$refs.confirmPrimary.focus();
                            }
                        });
                    });
                },

                resolveConfirmation(confirmed) {
                    const resolver = this.confirmation.resolver;
                    this.feedbackClosing = true;
                    this.confirmation.open = false;
                    this.confirmation.resolver = null;

                    if (resolver) {
                        resolver(confirmed);
                    }

                    this.$nextTick(() => {
                        this.feedbackClosing = false;
                    });
                },

                reloadWithToast(message) {
                    try {
                        window.sessionStorage.setItem('announcement-admin-toast', message);
                    } catch (error) {
                        this.showToast(message);
                    }
                    this.scheduleReload();
                },

                scheduleReload(delay = 0) {
                    if (this.reloadTimer) {
                        window.clearTimeout(this.reloadTimer);
                    }

                    if (delay <= 0) {
                        window.location.reload();
                        return;
                    }

                    this.reloadTimer = window.setTimeout(() => window.location.reload(), delay);
                }
            };
        }
    </script>
@endpush
