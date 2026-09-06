import React, { useMemo, useState } from "react";
import { Button, Segmented, Space, Spin } from "antd";
import { PrinterOutlined } from "@ant-design/icons";
import { useQuery } from "@tanstack/react-query";
import { useParams } from "react-router-dom";
import dayjs from "dayjs";
import api from "~/utils/api";
import { RATING_LEGEND, SECTION_LABELS } from "~/utils/constants";
import { useOrganization } from "~/hooks/useOrganization";
import RichTextView, { toPlainText } from "~/components/RichTextView";

function Signature({ label, name, title }) {
    return (
        <div>
            <div style={{ height: 46 }} />
            <div className="pms-signature-line">
                <strong>{name || " "}</strong>
                <div>{title || label}</div>
            </div>
        </div>
    );
}

/**
 * Who is answerable for an office target. The people it was actually assigned to
 * are the truth; the typed-in column is the fallback for targets nobody has been
 * handed yet, and for forms written before assignment existed.
 */
function accountableFor(indicator) {
    const assigned = (indicator?.children ?? [])
        .map((child) => child.output?.form?.owner?.name)
        .filter(Boolean);

    return assigned.length > 0 ? assigned.join(" / ") : (indicator?.accountable ?? "");
}

export default function PrintFormPage() {
    const { id } = useParams();
    const organization = useOrganization();
    const [periodId, setPeriodId] = useState(null);

    const { data } = useQuery({
        queryKey: ["print-form", id],
        queryFn: () => api.get(`reports/form/${id}/print`).then((r) => r.data),
    });

    const form = data?.form;
    const periods = form?.school_year?.periods ?? [];
    const formPeriodId = form?.type === "ipcr" ? (form?.rating_period_id ?? null) : null;
    const activePeriodId =
        formPeriodId ?? periodId ?? periods.find((p) => p.is_active)?.id ?? periods[0]?.id;

    const rows = useMemo(() => {
        if (!form) return [];

        const out = [];

        ["strategic", "core", "support"].forEach((section) => {
            const outputs = form.outputs.filter((o) => o.section === section);

            if (outputs.length === 0) return;

            out.push({ kind: "section", label: SECTION_LABELS[section] });

            outputs.forEach((output) => {
                output.indicators.forEach((indicator, index) => {
                    out.push({
                        kind: "indicator",
                        outputTitle: index === 0 ? output.title : null,
                        rowSpan: index === 0 ? output.indicators.length : 0,
                        indicator,
                    });
                });

                if (output.indicators.length === 0) {
                    out.push({ kind: "indicator", outputTitle: output.title, rowSpan: 1, indicator: null });
                }
            });
        });

        return out;
    }, [form]);

    if (!form) {
        return (
            <div style={{ display: "grid", placeItems: "center", minHeight: "100vh" }}>
                <Spin size="large" />
            </div>
        );
    }

    const isOpcr = form.type === "opcr";
    const summary = form.summaries?.find((s) => s.rating_period_id === activePeriodId);
    const period = periods.find((p) => p.id === activePeriodId);
    const rateeName = form.owner?.name ?? form.org_unit?.name;
    const columnCount = isOpcr ? 9 : 7;

    return (
        <div>
            <Space className="pms-no-print" style={{ padding: 16 }}>
                <Button type="primary" icon={<PrinterOutlined />} onClick={() => window.print()}>
                    Print
                </Button>
                {!isOpcr && !formPeriodId && periods.length > 1 && (
                    <Segmented
                        value={activePeriodId}
                        onChange={setPeriodId}
                        options={periods.map((p) => ({ value: p.id, label: p.label }))}
                    />
                )}
            </Space>

            <div className="pms-print-sheet">
                <h2 style={{ textAlign: "center", marginBottom: 16 }}>
                    {isOpcr
                        ? "OFFICE PERFORMANCE COMMITMENT AND REVIEW (OPCR)"
                        : "INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)"}
                </h2>

                <p style={{ fontSize: 12, marginBottom: 16 }}>
                    <strong>I, {rateeName?.toUpperCase()}</strong>
                    {isOpcr ? `, Head of the ${form.org_unit?.name},` : ` of the ${organization.name ?? ""}`} commit
                    to deliver and agree to be rated on the attainment of the following targets in accordance
                    with the indicated measures for the period{" "}
                    <strong>
                        {dayjs(form.school_year?.start_date).format("MMMM YYYY")} to{" "}
                        {dayjs(form.school_year?.end_date).format("MMMM YYYY")}
                    </strong>
                    {isOpcr ? " (January to December)" : period ? ` (${period.label})` : ""}.
                </p>

                <table style={{ marginBottom: 16 }}>
                    <tbody>
                        <tr>
                            <td style={{ width: "50%" }}>
                                <strong>Reviewed by</strong>
                                <div style={{ marginTop: 24 }}>
                                    <strong>
                                        {form.reviewed_by_name ||
                                            form.head_reviewer?.name ||
                                            form.vp_reviewer?.name ||
                                            " "}
                                    </strong>
                                </div>
                                <div>{form.head_reviewer?.position_title || "Immediate Supervisor"}</div>
                            </td>
                            <td>
                                <strong>Approved by</strong>
                                <div style={{ marginTop: 24 }}>
                                    <strong>
                                        {form.vp_reviewed_by_name ||
                                            form.vp_reviewer?.name ||
                                            " "}
                                    </strong>
                                </div>
                                <div>
                                    {form.vp_reviewer?.position_title ||
                                        organization.head_title ||
                                        "Head of Office"}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table>
                    <thead>
                        <tr>
                            <th rowSpan={2} style={{ width: 150 }}>
                                {isOpcr ? "MFO/PPA" : "Output"}
                            </th>
                            <th rowSpan={2} style={{ width: 220 }}>
                                Success Indicator
                                <div style={{ fontWeight: 400 }}>(Target + Measure)</div>
                            </th>
                            {isOpcr && <th rowSpan={2}>Allotted Budget</th>}
                            {isOpcr && <th rowSpan={2}>Individual / Accountable</th>}
                            <th rowSpan={2} style={{ width: 200 }}>
                                Actual Accomplishments
                            </th>
                            <th colSpan={4}>Rating</th>
                            <th rowSpan={2}>Remarks</th>
                        </tr>
                        <tr>
                            <th style={{ width: 34 }}>Q</th>
                            <th style={{ width: 34 }}>E</th>
                            <th style={{ width: 34 }}>T</th>
                            <th style={{ width: 42 }}>A</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => {
                            if (row.kind === "section") {
                                return (
                                    <tr key={`s-${index}`} className="pms-section-heading">
                                        <td colSpan={columnCount + 1}>
                                            <strong>{row.label.toUpperCase()}</strong>
                                        </td>
                                    </tr>
                                );
                            }

                            const indicator = row.indicator;
                            const accomplishment = indicator?.accomplishments?.find(
                                (a) => a.rating_period_id === activePeriodId
                            );
                            const rating = indicator?.ratings?.find(
                                (r) => r.rating_period_id === activePeriodId
                            );

                            return (
                                <tr key={`i-${index}`}>
                                    {row.rowSpan > 0 && (
                                        <td rowSpan={row.rowSpan}>{row.outputTitle}</td>
                                    )}
                                    <td className="pms-indicator-cell">
                                        <RichTextView html={indicator?.description} />
                                        {!isOpcr && indicator?.parent && (
                                            <div className="pms-indicator-source">
                                                Contributes to: {toPlainText(indicator.parent.description)}
                                            </div>
                                        )}
                                    </td>
                                    {isOpcr && (
                                        <td style={{ textAlign: "right" }}>
                                            {indicator?.allotted_budget != null
                                                ? `₱${Number(indicator.allotted_budget).toLocaleString()}`
                                                : ""}
                                        </td>
                                    )}
                                    {isOpcr && <td>{accountableFor(indicator)}</td>}
                                    <td className="pms-indicator-cell">
                                        <RichTextView html={accomplishment?.actual_accomplishment} />
                                        {accomplishment?.attachments?.length > 0 && (
                                            <div style={{ marginTop: 4, fontSize: 10 }}>
                                                Evidence:{" "}
                                                {accomplishment.attachments
                                                    .map((f) => f.original_name)
                                                    .join(", ")}
                                            </div>
                                        )}
                                    </td>
                                    <td style={{ textAlign: "center" }}>{rating?.q ?? ""}</td>
                                    <td style={{ textAlign: "center" }}>{rating?.e ?? ""}</td>
                                    <td style={{ textAlign: "center" }}>{rating?.t ?? ""}</td>
                                    <td style={{ textAlign: "center" }}>
                                        {rating?.a != null ? Number(rating.a).toFixed(2) : ""}
                                    </td>
                                    <td>
                                        <RichTextView html={rating?.remarks} />
                                    </td>
                                </tr>
                            );
                        })}

                        <tr>
                            <td colSpan={columnCount - 1} style={{ textAlign: "right" }}>
                                <strong>Final Average Rating</strong>
                            </td>
                            <td colSpan={2} style={{ textAlign: "center" }}>
                                <strong>
                                    {summary?.final_average != null
                                        ? Number(summary.final_average).toFixed(2)
                                        : ""}
                                </strong>
                            </td>
                        </tr>
                        <tr>
                            <td colSpan={columnCount - 1} style={{ textAlign: "right" }}>
                                <strong>Adjectival Rating</strong>
                            </td>
                            <td colSpan={2} style={{ textAlign: "center" }}>
                                <strong>{summary?.adjectival ?? ""}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style={{ marginTop: 12, fontSize: 11 }}>
                    <em>
                        Legend: Q – Quality &nbsp; E – Efficiency &nbsp; T – Timeliness &nbsp; A – Average
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        {RATING_LEGEND.map((b) => `${b.value} ${b.label} (${b.range})`).join("  ·  ")}
                    </em>
                </div>

                <div className="pms-signature">
                    <Signature label="Employee" name={rateeName} title="Ratee" />
                    <Signature
                        label="Supervisor"
                        name={form.reviewed_by_name}
                        title="Assessed by — Immediate Supervisor"
                    />
                    <Signature
                        label="Head of Office"
                        name={form.rated_by_name}
                        title="Final Rating by — Quality Assurance"
                    />
                </div>
            </div>
        </div>
    );
}
