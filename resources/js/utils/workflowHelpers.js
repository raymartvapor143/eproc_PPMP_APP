/**
 * Helper to determine if the currently logged-in user needs to explicitly "Receive"
 * the document before they can open details or act on it.
 *
 * Applicable workflow conditions:
 * 1. PENDING_APPROVAL: Supplemental/Amendment request submitted to Admin (admin_received_at is null)
 * 2. HEAD_PENDING: Office Head has not received (head_received_at is null)
 * 3. BUDGET_OFFICER_REVIEW: Budget Officer has not received (budget_received_at is null)
 * 4. OPPMO_REVIEW: OPPMO has not received (oppmo_received_at is null)
 * 5. TWG_REVIEW: TWG has not received (twg_received_at is null)
 * 6. Returned / Suspended states (HEAD_RETURNED, BUDGET_OFFICER_RETURNED, OPPMO_RETURNED, TWG_RETURNED):
 *    End User (creator) has not received the returned document (enduser_received_at is null)
 * 7. HEAD_APPROVED: End User (creator) has not received the approved PPMP (enduser_received_at is null)
 * 8. READY_TO_PRINT: End User (creator) has not received the fully approved PPMP (enduser_received_at is null)
 */
export const isAwaitingReceive = (ppmp, user) => {
    if (!ppmp || !user) return false;

    const status = ppmp.status;
    const role = user.role;

    // When an End User submits an Amendment / Supplemental request, it routes to Admin for approval
    if (ppmp.amendment_status === 'PENDING_APPROVAL') {
        return role === 'admin' && !ppmp.admin_received_at;
    }

    if (status === 'HEAD_PENDING') {
        const isTargetHead = (role === 'head' || role === 'authorized_staff') && (user.office_id === ppmp.office_id || !ppmp.office_id);
        return (isTargetHead || role === 'admin') && !ppmp.head_received_at;
    }

    if (status === 'BUDGET_OFFICER_REVIEW') {
        return (role === 'budget_officer' || role === 'admin') && !ppmp.budget_received_at;
    }

    if (status === 'OPPMO_REVIEW') {
        return (role === 'oppmo' || role === 'admin') && !ppmp.oppmo_received_at;
    }

    if (status === 'TWG_REVIEW') {
        return (role === 'twg' || role === 'admin') && !ppmp.twg_received_at;
    }

    if ([
        'HEAD_APPROVED',
        'READY_TO_PRINT',
        'HEAD_RETURNED',
        'BUDGET_OFFICER_RETURNED',
        'OPPMO_RETURNED',
        'TWG_RETURNED'
    ].includes(status)) {
        const isCreator = ppmp.created_by === user.id;
        return (isCreator || role === 'admin') && !ppmp.enduser_received_at;
    }

    return false;
};

/**
 * Returns metadata about the current stage's receipt state:
 * - recipientRole: string (e.g. 'Administrator', 'Office Head', 'Budget Officer', 'OPPMO', 'TWG', 'End User')
 * - isReceived: boolean (true if received for current stage)
 * - receivedAt: string | null (timestamp when received)
 * - canCurrentUserReceive: boolean (true if logged in user or admin can click Receive)
 */
export const getStageReceiptInfo = (ppmp, user) => {
    if (!ppmp) return null;

    const status = ppmp.status;
    const role = user?.role;

    let recipientRole = null;
    let isReceived = false;
    let receivedAt = null;
    let canCurrentUserReceive = false;

    if (ppmp.amendment_status === 'PENDING_APPROVAL') {
        recipientRole = 'Administrator';
        isReceived = Boolean(ppmp.admin_received_at);
        receivedAt = ppmp.admin_received_at;
        canCurrentUserReceive = role === 'admin' && !isReceived;
    } else if (status === 'HEAD_PENDING') {
        recipientRole = 'Office Head';
        isReceived = Boolean(ppmp.head_received_at);
        receivedAt = ppmp.head_received_at;
        const isTargetHead = (role === 'head' || role === 'authorized_staff') && (user?.office_id === ppmp.office_id || !ppmp.office_id);
        canCurrentUserReceive = (isTargetHead || role === 'admin') && !isReceived;
    } else if (status === 'BUDGET_OFFICER_REVIEW') {
        recipientRole = 'Budget Officer';
        isReceived = Boolean(ppmp.budget_received_at);
        receivedAt = ppmp.budget_received_at;
        canCurrentUserReceive = (role === 'budget_officer' || role === 'admin') && !isReceived;
    } else if (status === 'OPPMO_REVIEW') {
        recipientRole = 'OPPMO';
        isReceived = Boolean(ppmp.oppmo_received_at);
        receivedAt = ppmp.oppmo_received_at;
        canCurrentUserReceive = (role === 'oppmo' || role === 'admin') && !isReceived;
    } else if (status === 'TWG_REVIEW') {
        recipientRole = 'TWG';
        isReceived = Boolean(ppmp.twg_received_at);
        receivedAt = ppmp.twg_received_at;
        canCurrentUserReceive = (role === 'twg' || role === 'admin') && !isReceived;
    } else if ([
        'HEAD_APPROVED',
        'READY_TO_PRINT',
        'HEAD_RETURNED',
        'BUDGET_OFFICER_RETURNED',
        'OPPMO_RETURNED',
        'TWG_RETURNED'
    ].includes(status)) {
        recipientRole = 'End User';
        isReceived = Boolean(ppmp.enduser_received_at);
        receivedAt = ppmp.enduser_received_at;
        const isCreator = user && ppmp.created_by === user.id;
        canCurrentUserReceive = (isCreator || role === 'admin') && !isReceived;
    }

    if (!recipientRole) return null;

    return {
        recipientRole,
        isReceived,
        receivedAt,
        canCurrentUserReceive,
    };
};
