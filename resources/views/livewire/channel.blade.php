<?php

use function Livewire\Volt\mount;
use function Livewire\Volt\state;

state(['channel', 'messages', 'subscribed']);

mount(function ($channel) {
    $this->messages = $this->channel->getMessages()->toArray();
    $this->subscribed = $this->channel->isSubscribed(auth()->user());
});

$join = fn() => ($this->subscribed = $this->channel->subscribe(auth()->user()));

$send = fn(string $message) => $this->channel->send(auth()->user(), $message);

?>
<div x-data="channel" class="flex h-full w-full flex-col justify-between p-4 pb-2"
style="height: calc(100vh - 100px)">
<div x-show="newMessage">new message</div>
    <div class="messages mb-4 flex h-full grow flex-col overflow-y-scroll" x-ref="messages">
        @if ($subscribed)
            <span class="mt-auto w-full py-4 text-center text-lg"
                :class="{ 'mb-4 border-b': $wire.messages.length > 0 }">
                This is the very beginning of the
                <strong>{{ $channel->name }}</strong>
                channel.
            </span>

            <template x-for="message in $wire.messages">
                <div class="flex gap-x-2">
                    <img :src="message.user.avatar" :alt="message.user.name" class="h-10 w-10 rounded-md" />

                    <div>
                        <div class="flex items-center gap-x-2">
                            <span class="text-lg font-bold" x-text="message.user.name"></span>

                            <time class="text-sm text-gray-600" x-text="message.sent_at"></time>
                        </div>

                        <div x-html="message.content" class="text-lg"></div>
                    </div>
                </div>
            </template>
        @endif
    </div>

    <div class="flex w-full" @submitted.stop="send($event.detail.message)" @typing="typing">
        @if ($subscribed)
            <div class="flex w-full flex-col gap-y-1">
                <x-editor channel="{{ $channel->name }}" />

                <!-- Typing Indicator -->
                <span class="block shrink-0 text-xs text-gray-500 after:content-['\200b']" x-text="typingUsers"></span>
            </div>
        @else
            <div
                class="flex flex flex-grow flex-col items-center justify-center gap-y-4 rounded-md border bg-gray-100 p-6">
                <span class="text-lg font-bold">
                    #{{ $channel->name }}
                </span>

                <button type="submit" class="rounded-md bg-green-800 px-4 py-2 text-base text-white" wire:click="join">
                    Join channel
                </button>
            </div>
        @endif
    </div>
</div>

@script
    <script>
        Alpine.data('channel', () => {
            return {
                isTyping: false,

                usersTyping: [],

                channel: null,

                newMessage:false,

                init() {
                    this.scrollPosition()
                    this.channel = Echo.private('channels.{{ $channel->id }}')
                    this.channel.listen('MessageSent', (event) => {
                        this.$wire.messages.push(event.message)
                        this.newMessage = true
                    })
                    this.channel.listenForWhisper('StartTyping', (event) => {
                        this.usersTyping.push(event)
                    })
                    this.channel.listenForWhisper('StopTyping', (event) => {
                        this.usersTyping = this.usersTyping.filter(
                            (user) => user.id !== event.id
                        )
                    })
                    this.channel.listenForWhisper('typing11', (event) => {
                       console.log(event)
                    })
                },

                send(message) {
                    this.$wire.send(message)
                    this.channel.whisper('typing11', {
                        message
                    })
                },

                typing() {
                    this.debounce(
                        () => {
                            this.channel.whisper('StartTyping', {
                                id: '{{ auth()->id() }}',
                                name: '{{ auth()->user()->name }}'
                            })
                        },
                        () => {
                            this.channel.whisper('StopTyping', {
                                id: '{{ auth()->id() }}',
                                name: '{{ auth()->user()->name }}'
                            })
                        }
                    )
                },

                typingUsers() {
                    switch (this.usersTyping.length) {
                        case 0:
                            return ''
                        case 1:
                            return `${this.usersTyping[0].name} is typing...`
                        case 2:
                            return `${this.usersTyping[0].name} and ${this.usersTyping[1].name} are typing...`
                        default:
                            return 'Several people are typing...'
                    }
                },

                scrollPosition() {
                    this.$watch('$wire.messages', () => {
                        this.$refs.messages.scrollTop =
                            this.$refs.messages.scrollHeight;
                    });
                },

                debouncer: null,

                debounce(startCallback, stopCallback) {
                    if (this.debouncer) {
                        clearTimeout(this.debouncer)
                    }

                    this.debouncer = setTimeout(() => {
                        this.isTyping = false

                        stopCallback();
                    }, 2000);

                    if (!this.isTyping) {
                        this.isTyping = true;

                        startCallback();
                    }
                },
            }
        })
    </script>
@endscript
