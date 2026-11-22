import { Form, Head, Link } from '@inertiajs/react';
import { UsersIcon } from '@phosphor-icons/react';

import TeamInvitationController from '@/actions/App/Http/Controllers/TeamInvitationController';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { login, register } from '@/routes';
import { type TeamInvitation } from '@/types';

interface TeamWithName {
    id: number;
    name: string;
}

interface Props {
    invitation: TeamInvitation & { team: TeamWithName };
    token: string;
    signedInEmail: string | null;
}

export default function AcceptInvitation({
    invitation,
    token,
    signedInEmail,
}: Props) {
    return (
        <AuthLayout
            title="Team invitation"
            description={`You've been invited to join ${invitation.team.name}`}
        >
            <Head title="Accept team invitation" />

            <div className="space-y-6 text-center">
                <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-muted">
                    <UsersIcon className="size-8 text-muted-foreground" />
                </div>

                <div className="space-y-1">
                    <p className="text-sm">
                        You have been invited to join{' '}
                        <span className="font-semibold">
                            {invitation.team.name}
                        </span>{' '}
                        as a{' '}
                        <span className="font-semibold capitalize">
                            {invitation.role}
                        </span>
                        .
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {signedInEmail
                            ? `You're signed in as ${signedInEmail}.`
                            : 'Sign in or create an account to accept this invitation.'}
                    </p>
                </div>

                {signedInEmail ? (
                    <Form {...TeamInvitationController.confirm.form(token)}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                Join {invitation.team.name}
                            </Button>
                        )}
                    </Form>
                ) : (
                    <div className="flex flex-col gap-3">
                        <Button asChild>
                            <Link href={login()}>Sign in to accept</Link>
                        </Button>

                        <Button variant="outline" asChild>
                            <Link href={register()}>Create an account</Link>
                        </Button>
                    </div>
                )}

                <p className="text-xs text-muted-foreground">
                    The invitation was sent to{' '}
                    <span className="font-medium">{invitation.email}</span>. You
                    must sign in with that email address to accept it.
                </p>
            </div>
        </AuthLayout>
    );
}
