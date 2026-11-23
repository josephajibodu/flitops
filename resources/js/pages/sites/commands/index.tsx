import { Head, router, usePage } from '@inertiajs/react';
import {
    CircleNotchIcon,
    DotsThreeVerticalIcon,
    FileTextIcon,
    PencilSimpleIcon,
    PlayIcon,
    TerminalWindowIcon,
} from '@phosphor-icons/react';
import { useEffect, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { StatusBadge, type StatusColor } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    getPaginationUrls,
    Pagination,
    type PaginationMeta,
} from '@/components/ui/pagination';
import {
    Sheet,
    SheetBody,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { getSiteSubNavItems } from '@/config/sub-nav-items';
import { usePermission } from '@/hooks/use-permission';
import { useTeamPath } from '@/hooks/use-team-path';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Site } from '@/types/site';

interface CommandRun {
    id: number;
    command: string;
    status: string;
    status_label: string;
    status_color: StatusColor;
    exit_code: number | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string;
    updated_at: string;
    user?: { id: number; name: string };
}

interface Props {
    server: { data: { id: number; name: string } };
    site: {
        data: Site & {
            server?: { id: number; name: string; ip_address: string };
        };
    };
    commandRuns: {
        data: CommandRun[];
        links?: unknown;
        meta?: PaginationMeta;
    };
}

function truncateCommand(cmd: string, maxLen: number = 60): string {
    if (cmd.length <= maxLen) return cmd;
    return cmd.slice(0, maxLen) + '…';
}

export default function SiteCommandsIndex({
    server: serverProp,
    site: siteProp,
    commandRuns,
}: Props) {
    const { currentTeam } = usePage<SharedData>().props;
    const teamPath = useTeamPath();
    const can = usePermission();
    const canRunCommands = can('run_commands');
    const canViewOutput = can('view_secrets');
    const server = serverProp?.data ?? serverProp;
    const site = siteProp?.data ?? siteProp;
    const serverId = server?.id ?? site?.server?.id;
    const siteId = site?.id;

    const [runSheetOpen, setRunSheetOpen] = useState(false);
    const [commandInput, setCommandInput] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [logsDialogOpen, setLogsDialogOpen] = useState(false);
    const [logsRunId, setLogsRunId] = useState<number | null>(null);
    const [logsRunFromOtherPage, setLogsRunFromOtherPage] =
        useState<CommandRun | null>(null);
    const [logsOutput, setLogsOutput] = useState<{
        runId: number;
        content: string;
    } | null>(null);

    const runs = commandRuns?.data ?? [];
    const { prevUrl, nextUrl } = getPaginationUrls(commandRuns?.links);

    /**
     * The run shown in the logs dialog. Read from the live `commandRuns` prop
     * so its status updates while the dialog is open.
     */
    const logsRun =
        runs.find((run) => run.id === logsRunId) ??
        (logsRunFromOtherPage?.id === logsRunId ? logsRunFromOtherPage : null);
    const isLogsRunFinished = Boolean(logsRun?.finished_at);
    const logsOutputUrl = logsRunId
        ? teamPath(
              `/servers/${serverId}/sites/${siteId}/command-runs/${logsRunId}`,
          )
        : null;

    useEffect(() => {
        if (
            !logsDialogOpen ||
            !logsOutputUrl ||
            !logsRunId ||
            !isLogsRunFinished
        ) {
            return;
        }

        const runId = logsRunId;
        const controller = new AbortController();
        fetch(logsOutputUrl, {
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json())
            .then((data) =>
                setLogsOutput({ runId, content: data.output ?? '' }),
            )
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    setLogsOutput({ runId, content: 'Failed to load output.' });
                }
            });

        return () => controller.abort();
    }, [logsDialogOpen, logsOutputUrl, logsRunId, isLogsRunFinished]);

    const logsContent =
        logsOutput?.runId === logsRunId ? logsOutput.content : null;
    const logsLoading = isLogsRunFinished && logsContent === null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Servers', href: teamPath('/servers') },
        {
            title: server?.name || site?.server?.name || 'Server',
            href: teamPath(`/servers/${serverId}`),
        },
        {
            title: site?.domain ?? 'Site',
            href: teamPath(`/servers/${serverId}/sites/${siteId}`),
        },
        {
            title: 'Commands',
            href: teamPath(`/servers/${serverId}/sites/${siteId}/command-runs`),
        },
    ];

    const openLogs = (run: CommandRun) => {
        setLogsRunId(run.id);
        setLogsRunFromOtherPage(run);
        setLogsOutput(null);
        setLogsDialogOpen(true);
    };

    /**
     * Queue a command, then open the logs dialog on the new run so the output
     * appears there as soon as the command finishes.
     */
    const runCommand = (command: string, onSuccess?: () => void) => {
        if (!serverId || !siteId) return;
        setIsSubmitting(true);
        router.post(
            teamPath(`/servers/${serverId}/sites/${siteId}/command-runs`),
            { command },
            {
                preserveScroll: true,
                onFlash: ({ commandRunId }) => {
                    if (canViewOutput && commandRunId) {
                        setLogsRunId(commandRunId);
                        setLogsRunFromOtherPage(null);
                        setLogsOutput(null);
                        setLogsDialogOpen(true);
                    }
                },
                onSuccess,
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const handleRunCommand = (e: React.FormEvent) => {
        e.preventDefault();
        if (isSubmitting || !commandInput.trim()) return;
        runCommand(commandInput.trim(), () => {
            setRunSheetOpen(false);
            setCommandInput('');
        });
    };

    /** Enter runs the command; Shift+Enter adds a new line, like a terminal. */
    const handleCommandKeyDown = (
        e: React.KeyboardEvent<HTMLTextAreaElement>,
    ) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
            e.preventDefault();
            e.currentTarget.form?.requestSubmit();
        }
    };

    const handleRunAgain = (run: CommandRun) => {
        runCommand(run.command);
    };

    const handleEditAndRun = (run: CommandRun) => {
        setCommandInput(run.command);
        setRunSheetOpen(true);
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            subNavItems={getSiteSubNavItems(
                currentTeam?.slug ?? '',
                String(serverId ?? ''),
                String(siteId ?? ''),
            )}
        >
            <Head title={`Commands - ${site?.domain ?? 'Site'}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Commands
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Run commands on the server in this site’s directory.
                        </p>
                    </div>
                    {canRunCommands && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setRunSheetOpen(true)}
                        >
                            <TerminalWindowIcon className="h-4 w-4" />
                            Run command
                        </Button>
                    )}
                </div>

                <div className="space-y-4">
                    {runs.length === 0 ? (
                        <EmptyState
                            title="No command runs yet"
                            description={
                                canRunCommands
                                    ? 'Run a command to see output and history here.'
                                    : 'Commands run on this site will appear here.'
                            }
                        />
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Command</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Exit
                                        </TableHead>
                                        <TableHead>Finished</TableHead>
                                        <TableHead className="w-0" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {runs.map((run) => (
                                        <TableRow key={run.id}>
                                            <TableCell className="max-w-[320px] truncate">
                                                {truncateCommand(run.command)}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={run.status_label}
                                                    color={run.status_color}
                                                    pulse={
                                                        run.status === 'running'
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground">
                                                {run.exit_code ?? '—'}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                                {run.finished_at
                                                    ? new Date(
                                                          run.finished_at,
                                                      ).toLocaleString()
                                                    : run.started_at
                                                      ? 'Running…'
                                                      : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-end gap-2">
                                                    {canViewOutput && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-8"
                                                            onClick={() =>
                                                                openLogs(run)
                                                            }
                                                        >
                                                            <FileTextIcon className="h-4 w-4" />
                                                            View logs
                                                        </Button>
                                                    )}
                                                    {canRunCommands && (
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="outline"
                                                                    size="icon"
                                                                    className="h-8 w-8"
                                                                    aria-label="Actions"
                                                                >
                                                                    <DotsThreeVerticalIcon className="h-4 w-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        handleRunAgain(
                                                                            run,
                                                                        )
                                                                    }
                                                                >
                                                                    <PlayIcon className="h-4 w-4" />
                                                                    Run again
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        handleEditAndRun(
                                                                            run,
                                                                        )
                                                                    }
                                                                >
                                                                    <PencilSimpleIcon className="h-4 w-4" />
                                                                    Edit and run
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            {(prevUrl || nextUrl) && (
                                <Pagination
                                    prevUrl={prevUrl}
                                    nextUrl={nextUrl}
                                    meta={commandRuns.meta}
                                />
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* Run command sheet */}
            <Sheet open={runSheetOpen} onOpenChange={setRunSheetOpen}>
                <SheetContent>
                    <form onSubmit={handleRunCommand} className="contents">
                        <SheetHeader>
                            <SheetTitle>Run command</SheetTitle>
                        </SheetHeader>
                        <SheetBody className="gap-2">
                            <label
                                htmlFor="command-input"
                                className="block text-sm font-medium text-muted-foreground"
                            >
                                Command (runs in site root on the server)
                            </label>
                            <textarea
                                id="command-input"
                                value={commandInput}
                                onChange={(e) =>
                                    setCommandInput(e.target.value)
                                }
                                onKeyDown={handleCommandKeyDown}
                                placeholder="e.g. php artisan --version"
                                className="min-h-[120px] w-full resize-y rounded-md border border-input bg-muted/50 px-3 py-2 font-mono text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                rows={4}
                            />
                            <p className="text-xs text-muted-foreground">
                                Press Enter to run, Shift+Enter for a new line.
                            </p>
                        </SheetBody>
                        <SheetFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRunSheetOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isSubmitting || !commandInput.trim()}
                            >
                                {isSubmitting ? (
                                    <>
                                        <CircleNotchIcon className="h-4 w-4 animate-spin" />
                                        Running…
                                    </>
                                ) : (
                                    <>
                                        <PlayIcon className="h-4 w-4" />
                                        Run
                                    </>
                                )}
                            </Button>
                        </SheetFooter>
                    </form>
                </SheetContent>
            </Sheet>

            {/* View logs dialog */}
            <Dialog open={logsDialogOpen} onOpenChange={setLogsDialogOpen}>
                <DialogContent className="max-h-[85vh] gap-0 overflow-hidden border-zinc-200 bg-zinc-50 p-0 sm:max-w-3xl dark:border-zinc-800 dark:bg-zinc-950">
                    <DialogHeader className="flex h-12 flex-row items-center gap-2 border-b border-zinc-200 bg-zinc-100 pr-12 pl-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <span className="h-3 w-3 shrink-0 rounded-full bg-red-500/80" />
                        <span className="h-3 w-3 shrink-0 rounded-full bg-yellow-500/80" />
                        <span className="h-3 w-3 shrink-0 rounded-full bg-green-500/80" />
                        <DialogTitle className="ml-2 truncate font-mono text-xs font-normal text-zinc-500 dark:text-zinc-400">
                            {site?.domain ?? 'site'}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="max-h-[70vh] min-h-[280px] overflow-y-auto p-4 font-mono text-[13px] leading-relaxed text-zinc-900 dark:text-zinc-100">
                        {logsRun && (
                            <div className="break-all whitespace-pre-wrap">
                                <span className="text-green-600 dark:text-green-400">
                                    ${' '}
                                </span>
                                {logsRun.command}
                            </div>
                        )}
                        {!isLogsRunFinished || logsLoading ? (
                            <p className="mt-1 flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                                <CircleNotchIcon className="h-4 w-4 animate-spin" />
                                {isLogsRunFinished
                                    ? 'Loading…'
                                    : logsRun?.started_at
                                      ? 'Running…'
                                      : 'Waiting to start…'}
                            </p>
                        ) : (
                            <>
                                <pre className="mt-1 break-words whitespace-pre-wrap">
                                    {logsContent || (
                                        <span className="text-zinc-500">
                                            No output.
                                        </span>
                                    )}
                                </pre>
                                {logsRun?.exit_code !== null &&
                                    logsRun?.exit_code !== undefined && (
                                        <p
                                            className={
                                                logsRun.exit_code === 0
                                                    ? 'mt-3 text-zinc-500'
                                                    : 'mt-3 text-red-600 dark:text-red-400'
                                            }
                                        >
                                            [exited with code{' '}
                                            {logsRun.exit_code}]
                                        </p>
                                    )}
                            </>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
