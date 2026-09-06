import React from "react";
import { Divider, Space, Tag, Tooltip, Typography } from "antd";
import { ClockCircleOutlined, ExclamationCircleOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import { DELAY_META } from "~/utils/constants";
import CommentThread from "~/components/CommentThread";
import DeliveredBy from "~/components/DeliveredBy";
import ProgressCell from "~/components/ProgressCell";
import RichTextView from "~/components/RichTextView";

export default function OpcrLineDrawer({ line, output, form, periodId, canAssign = false }) {
    const rating = (line.ratings ?? []).find((r) => r.rating_period_id === periodId);
    const hasDelegatedHeading = Number(output?.child_outputs_count ?? 0) > 0;

    return (
        <>
            <RichTextView html={line.description} />

            <Space wrap size={8} style={{ marginTop: 12 }}>
                {line.delay && line.delay.state !== "no_date" && (
                    <Tooltip title={line.delay.label}>
                        <Tag
                            color={DELAY_META[line.delay.state]?.color}
                            icon={
                                ["overdue", "late"].includes(line.delay.state) ? (
                                    <ExclamationCircleOutlined />
                                ) : (
                                    <ClockCircleOutlined />
                                )
                            }
                        >
                            {DELAY_META[line.delay.state]?.label}
                        </Tag>
                    </Tooltip>
                )}
                {line.target_date && (
                    <Typography.Text type="secondary">
                        Target date: {dayjs(line.target_date).format("MMM D, YYYY")}
                    </Typography.Text>
                )}
                {line.allotted_budget != null && (
                    <Typography.Text type="secondary">
                        Budget: ₱{Number(line.allotted_budget).toLocaleString()}
                    </Typography.Text>
                )}
            </Space>

            <div style={{ marginTop: 12, maxWidth: 260 }}>
                <ProgressCell
                    status={line.progress_status}
                    pct={line.progress_pct}
                    computed={(line.children ?? []).length > 0 || hasDelegatedHeading}
                />
            </div>

            {rating?.a != null && (
                <Space wrap size={6} style={{ marginTop: 12 }}>
                    <Tag>Q {rating.q ?? "—"}</Tag>
                    <Tag>E {rating.e ?? "—"}</Tag>
                    <Tag>T {rating.t ?? "—"}</Tag>
                    <Tag color="purple">A = {Number(rating.a).toFixed(2)}</Tag>
                </Space>
            )}

            <Divider orientation="left" orientationMargin={0}>
                Delivered by
            </Divider>

            <DeliveredBy
                indicatorId={line.id}
                formId={form.id}
                periodId={periodId}
                canAssign={canAssign}
                hasDelegatedHeading={hasDelegatedHeading}
            />

            <Divider orientation="left" orientationMargin={0}>
                Remarks on this office target
            </Divider>

            <CommentThread formId={form.id} indicatorId={line.id} compact />
        </>
    );
}
