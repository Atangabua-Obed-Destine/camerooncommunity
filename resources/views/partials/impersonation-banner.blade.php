{{-- Shown whenever an admin is signed in as someone else.

     Without a permanent, unmissable way back, impersonation is how an admin
     accidentally acts as a member for the rest of the day. It sits at the very
     top of every authenticated page and cannot be dismissed. --}}
@if(session('impersonator_id'))
    <div class="sticky top-0 z-[200] flex flex-wrap items-center justify-center gap-3 bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-amber-950">
        <span>
            You are viewing the app as
            <strong>{{ auth()->user()->username ?? auth()->user()->name }}</strong>.
            Anything you do here is done as them.
        </span>

        <form method="POST" action="{{ route('impersonate.stop') }}">
            @csrf
            <button class="rounded-full bg-amber-950 px-3 py-1 text-xs font-bold text-amber-50 hover:bg-amber-900">
                Return to admin
            </button>
        </form>
    </div>
@endif
