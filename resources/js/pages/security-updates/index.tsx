import { Head, router } from '@inertiajs/react';
import { format, formatDistanceToNow } from 'date-fns';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { CopyButton } from '@/components/copy-button';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge, type StatusColor } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { CardDescription, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import { useTeamPath } from '@/hooks/use-team-path';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface ManualStep {
    title: string;
    explanation: string;
    command: string | null;
}

interface ServerCheck {
    id: number;
    server: { id: number; name: string; ip_address: string | null };
    status: string;
    status_label: string;
    status_color: StatusColor;
    is_busy: boolean;
    can_fix: boolean;
    detail: string | null;
    output: string | null;
    checked_at: string | null;
    fixed_at: string | null;
    fixed_by: string | null;
    manual_steps: ManualStep[];
}

interface Advisory {
    key: string;
    title: string;
    severity: 'critical' | 'high';
    published_on: string;
    summary: string;
    fix_description: string;
    reference_url: string | null;
    servers: ServerCheck[];
}

interface Props {
    advisories: Advisory[];
}

export default function SecurityUpdatesIndex({ advisories }: Props) {
    const teamPath = useTeamPath();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Security updates', href: teamPath('/security-updates') },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Security updates" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {advisories.length === 0 ? (
                    <EmptyState
                        title="No security updates"
                        description="When a security fix affects one of your servers, it shows up here. Nothing is installed until you ask."
                        points={[
                            'Check a server without changing anything',
                            'Let Flitops install the fix, or do it yourself',
                            'Step-by-step guide for every fix',
                        ]}
                    />
                ) : (
                    advisories.map((advisory) => (
                        <AdvisoryCard key={advisory.key} advisory={advisory} />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

function AdvisoryCard({ advisory }: { advisory: Advisory }) {
    const teamPath = useTeamPath();
    const can = usePermission();
    const canCheck = can('view');
    const canFix = can('manage_servers');

    const [checkToFix, setCheckToFix] = useState<ServerCheck | null>(null);
    const [isStartingFix, setIsStartingFix] = useState(false);
    const [guideFor, setGuideFor] = useState<ServerCheck | null>(null);
    const [logFor, setLogFor] = useState<ServerCheck | null>(null);

    const protectedCount = advisory.servers.filter(
        (check) => check.status === 'covered',
    ).length;

    const runCheck = (check: ServerCheck) => {
        router.post(
            teamPath(`/security-updates/${check.id}/check`),
            {},
            { preserveScroll: true },
        );
    };

    const confirmFix = () => {
        if (!checkToFix) {
            return;
        }

        setIsStartingFix(true);
        router.post(
            teamPath(`/security-updates/${checkToFix.id}/fix`),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsStartingFix(false);
                    setCheckToFix(null);
                },
            },
        );
    };

    return (
        <section className="rounded-2xl bg-card shadow-card">
            <div className="space-y-3 border-b px-6 py-5">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <CardTitle>{advisory.title}</CardTitle>
                    <StatusBadge
                        status={
                            advisory.severity === 'critical'
                                ? 'Critical'
                                : 'High'
                        }
                        color={
                            advisory.severity === 'critical' ? 'red' : 'orange'
                        }
                    />
                </div>
                <CardDescription className="max-w-3xl leading-relaxed">
                    {advisory.summary}
                </CardDescription>
                <p className="text-sm text-muted-foreground">
                    Published{' '}
                    {format(new Date(advisory.published_on), 'd MMM yyyy')}
                    {' · '}
                    {protectedCount} of {advisory.servers.length}{' '}
                    {advisory.servers.length === 1 ? 'server' : 'servers'}{' '}
                    protected
                    {advisory.reference_url && (
                        <>
                            {' · '}
                            <a
                                href={advisory.reference_url}
                                target="_blank"
                                rel="noreferrer"
                                className="underline underline-offset-4 hover:text-foreground"
                            >
                                Read the official notice
                            </a>
                        </>
                    )}
                </p>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="pl-6">Server</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="pr-6 text-right">
                            Actions
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {advisory.servers.map((check) => (
                        <TableRow key={check.id}>
                            <TableCell className="pl-6 align-top">
                                <div className="font-medium">
                                    {check.server.name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {check.server.ip_address}
                                </div>
                            </TableCell>
                            <TableCell className="max-w-xl align-top whitespace-normal">
                                <StatusBadge
                                    status={check.status_label}
                                    color={check.status_color}
                                    pulse={check.is_busy}
                                />
                                <p className="mt-1.5 text-sm text-muted-foreground">
                                    {describeCheck(check)}
                                </p>
                            </TableCell>
                            <TableCell className="pr-6 text-right align-top">
                                <div className="flex flex-wrap justify-end gap-2">
                                    {check.output && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-xs"
                                            onClick={() => setLogFor(check)}
                                        >
                                            View log
                                        </Button>
                                    )}
                                    {check.can_fix && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="text-xs"
                                            onClick={() => setGuideFor(check)}
                                        >
                                            Show me how
                                        </Button>
                                    )}
                                    {check.can_fix && canFix && (
                                        <Button
                                            size="sm"
                                            className="text-xs"
                                            onClick={() => setCheckToFix(check)}
                                        >
                                            Fix it for me
                                        </Button>
                                    )}
                                    {canCheck && check.status !== 'fixing' && (
                                        <Button
                                            size="sm"
                                            variant={
                                                check.status === 'unchecked'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className="text-xs"
                                            disabled={check.is_busy}
                                            onClick={() => runCheck(check)}
                                        >
                                            {check.status === 'unchecked'
                                                ? 'Check'
                                                : 'Check again'}
                                        </Button>
                                    )}
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            <ConfirmDialog
                open={checkToFix !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCheckToFix(null);
                    }
                }}
                title={`Install the update on ${checkToFix?.server.name ?? ''}?`}
                description={advisory.fix_description}
                confirmLabel="Install update"
                loading={isStartingFix}
                onConfirm={confirmFix}
            />

            <ManualGuideDialog
                check={guideFor}
                onClose={() => setGuideFor(null)}
            />

            <Dialog
                open={logFor !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setLogFor(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            Update log for {logFor?.server.name}
                        </DialogTitle>
                        <DialogDescription>
                            The commands Flitops ran on the server and what each
                            one printed.
                        </DialogDescription>
                    </DialogHeader>
                    <pre className="max-h-[60vh] overflow-auto rounded-lg border bg-muted p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap">
                        {logFor?.output}
                    </pre>
                </DialogContent>
            </Dialog>
        </section>
    );
}

function ManualGuideDialog({
    check,
    onClose,
}: {
    check: ServerCheck | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={check !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Fix {check?.server.name} yourself</DialogTitle>
                    <DialogDescription>
                        Run these commands on the server, in order. Each step
                        says what it does and what to look for.
                    </DialogDescription>
                </DialogHeader>
                <ol className="max-h-[65vh] space-y-5 overflow-y-auto pr-1">
                    {check?.manual_steps.map((step, index) => (
                        <li key={step.title} className="flex gap-3">
                            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                {index + 1}
                            </span>
                            <div className="min-w-0 flex-1 space-y-2">
                                <p className="text-sm font-medium">
                                    {step.title}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {step.explanation}
                                </p>
                                {step.command && (
                                    <div className="flex min-w-0 items-start gap-1 rounded-lg border bg-muted p-2.5">
                                        <code className="min-w-0 flex-1 font-mono text-xs leading-relaxed break-words whitespace-pre-wrap">
                                            {step.command}
                                        </code>
                                        <CopyButton
                                            value={step.command}
                                            className="shrink-0"
                                        />
                                    </div>
                                )}
                            </div>
                        </li>
                    ))}
                </ol>
            </DialogContent>
        </Dialog>
    );
}

function describeCheck(check: ServerCheck): string {
    if (check.status === 'unchecked') {
        return 'Not checked yet. Checking only reads from the server and changes nothing.';
    }

    if (check.status === 'checking') {
        return 'Checking the server…';
    }

    if (check.status === 'fixing') {
        return 'Installing the update. This usually takes a minute or two.';
    }

    const detail = check.detail ?? '';

    if (check.status === 'covered' && check.fixed_at) {
        const when = formatDistanceToNow(new Date(check.fixed_at), {
            addSuffix: true,
        });

        return `${detail} Updated by Flitops ${when}${check.fixed_by ? ` (${check.fixed_by})` : ''}.`;
    }

    return detail;
}
