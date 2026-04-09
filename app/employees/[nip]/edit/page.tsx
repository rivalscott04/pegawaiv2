"use client";

import { useEffect, useRef, useState } from "react";
import type { PegawaiFiltersResponseV2, PegawaiV2 } from "@/lib/types";
import { useParams, useRouter } from "next/navigation";
import { apiFetch, canEditEmployees, getRole, hasPermission } from "@/lib/api";
import { CANONICAL_KANWIL_INDUK_NAME, KANWIL_SOURCE_UNIT_SLUG } from "@/lib/ntb-constants";

/** Pegawai.edit saja: tidak mengubah NIP lama / penempatan wilayah (source unit). */
const LIMITED_EXCLUDED = new Set(["nip_lama", "source_unit_slug"]);

export default function EmployeeEditPage() {
  const params = useParams<{ nip: string }>();
  const router = useRouter();
  const nip = params.nip;
  const [data, setData] = useState<PegawaiV2 | null>(null);
  const [slugOptions, setSlugOptions] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string>("");
  const [modalMessage, setModalMessage] = useState<string>("");
  const successModalRef = useRef<HTMLDialogElement>(null);
  const errorModalRef = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    if (typeof window === "undefined") return;

    if (!nip || nip.trim() === "") {
      setError("Parameter NIP tidak valid");
      setLoading(false);
      return;
    }

    const token = localStorage.getItem("token") || sessionStorage.getItem("token");
    if (!token) {
      router.replace("/auth/login");
      return;
    }

    async function load() {
      try {
        const enc = encodeURIComponent(nip);
        const [detailRes, filtersRes] = await Promise.all([
          apiFetch<{ success: boolean; data: PegawaiV2 }>(`/pegawai/${enc}`),
          apiFetch<PegawaiFiltersResponseV2>(`/pegawai/filters?limit=120`),
        ]);
        if (!detailRes.data) {
          setError("Data pegawai tidak ditemukan");
          return;
        }
        setData(detailRes.data);
        const fromFilters = filtersRes.source_unit_slug ?? [];
        const slugSet = new Set(fromFilters);
        if (detailRes.data.source_unit_slug) {
          slugSet.add(detailRes.data.source_unit_slug);
        }
        setSlugOptions([...slugSet].sort((a, b) => a.localeCompare(b)));
      } catch (err: unknown) {
        const errorMsg = err instanceof Error ? err.message : "Gagal memuat data pegawai";
        setError(errorMsg);
        if (errorMsg.includes("fetch") || errorMsg.includes("network")) {
          return;
        }
      } finally {
        setLoading(false);
      }
    }
    void load();
  }, [nip, router]);

  const lockedByKanwilAdmin =
    getRole() === "admin_kanwil" && data != null && data.source_unit_slug !== KANWIL_SOURCE_UNIT_SLUG;

  function canEditField(name: string): boolean {
    if (name === "nip") return false;
    if (lockedByKanwilAdmin) return false;
    if (hasPermission("pegawai.edit_all")) return true;
    if (hasPermission("pegawai.edit")) return !LIMITED_EXCLUDED.has(name);
    return false;
  }

  const readOnly = !canEditEmployees() || lockedByKanwilAdmin;

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!data || readOnly) return;

    setError("");
    setSaving(true);
    try {
      const fd = new FormData(e.currentTarget);
      const payload: Record<string, string | boolean> = {};

      const textKeys = [
        "nama",
        "nip_lama",
        "tempat_tanggal_lahir",
        "jenis_kelamin",
        "agama",
        "jenis_pegawai",
        "jabatan",
        "unit_kerja",
        "satker_induk",
        "pangkat_golongan",
        "pendidikan_terakhir",
        "source_unit_slug",
      ] as const;

      for (const key of textKeys) {
        if (!canEditField(key)) continue;
        const v = fd.get(key);
        if (typeof v === "string") payload[key] = v;
      }

      if (canEditField("is_active")) {
        payload.is_active = fd.get("is_active") === "1";
      }

      await apiFetch(`/pegawai/${encodeURIComponent(nip)}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      setModalMessage("Data pegawai berhasil diperbarui!");
      successModalRef.current?.showModal();
    } catch (err: unknown) {
      let errorMsg = "Gagal menyimpan perubahan data pegawai";
      const originalError = err instanceof Error ? err.message : "";

      if (originalError.includes("fetch") || originalError.includes("network") || originalError.includes("Failed to fetch")) {
        errorMsg = "Gagal menyimpan: Tidak dapat terhubung ke server. Periksa koneksi internet Anda.";
      } else if (originalError.includes("401") || originalError.includes("Unauthorized")) {
        errorMsg = "Gagal menyimpan: Sesi Anda telah berakhir. Silakan login kembali.";
      } else if (originalError.includes("403") || originalError.includes("Forbidden")) {
        errorMsg = "Gagal menyimpan: Anda tidak memiliki izin untuk mengubah data ini.";
      } else if (originalError.includes("404") || originalError.includes("Not Found")) {
        errorMsg = "Gagal menyimpan: Data pegawai tidak ditemukan di server.";
      } else if (originalError.includes("422") || originalError.includes("Unprocessable")) {
        errorMsg = `Gagal menyimpan: ${originalError}`;
      } else if (originalError.includes("500") || originalError.includes("Internal Server Error")) {
        errorMsg = "Gagal menyimpan: Terjadi kesalahan pada server. Silakan coba lagi nanti.";
      } else if (originalError.includes("429") || originalError.includes("Too Many Requests")) {
        errorMsg = "Gagal menyimpan: Terlalu banyak permintaan. Silakan tunggu beberapa saat dan coba lagi.";
      } else if (originalError.trim() !== "") {
        errorMsg = `Gagal menyimpan: ${originalError}`;
      }

      setError(errorMsg);
      setModalMessage(errorMsg);
      errorModalRef.current?.showModal();
    } finally {
      setSaving(false);
    }
  }

  function handleSuccessModalClose() {
    successModalRef.current?.close();
    router.replace(`/employees`);
  }

  function handleErrorModalClose() {
    errorModalRef.current?.close();
  }

  if (loading)
    return (
      <div className="p-6 flex items-center justify-center min-h-[60vh]">
        <span className="loading loading-spinner loading-lg"></span>
      </div>
    );

  if (error && !data)
    return (
      <div className="p-6">
        <div className="alert alert-error">
          <span>{error}</span>
        </div>
        <a href="/employees" className="btn btn-primary mt-4">
          Kembali ke Daftar
        </a>
      </div>
    );

  if (!data && !loading) {
    return (
      <div className="p-6">
        <div className="alert alert-error">
          <span>Data pegawai tidak ditemukan untuk NIP: {nip}</span>
        </div>
        <a href="/employees" className="btn btn-primary mt-4">
          Kembali ke Daftar
        </a>
      </div>
    );
  }

  return (
    <div className="p-4 md:p-8 max-w-5xl mx-auto">
      <div className="mb-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <div className="breadcrumbs text-sm mb-2">
              <ul>
                <li>
                  <a href="/employees">Daftar Pegawai</a>
                </li>
                <li>Edit — {data?.nama || nip}</li>
              </ul>
            </div>
            <h1 className="text-3xl font-bold">Edit data pegawai</h1>
            <p className="text-sm opacity-70 mt-1">Skema tabel pegawai (sumber API `/pegawai`)</p>
          </div>
          <a className="btn btn-outline" href="/employees">
            Kembali
          </a>
        </div>
      </div>

      {error && (
        <div className="alert alert-error mb-4">
          <span>{error}</span>
        </div>
      )}

      <form onSubmit={onSubmit}>
        <fieldset className="fieldset mb-4 border border-base-300 rounded-lg p-6 bg-base-100">
          <legend className="fieldset-legend text-lg font-bold px-2">Identitas</legend>
          <div className="grid md:grid-cols-2 gap-6 mt-4">
            <div className="form-control">
              <label className="label">
                <span className="label-text font-medium">NIP</span>
              </label>
              <input name="nip" type="text" value={data?.nip || ""} disabled className="input input-bordered bg-base-200" />
            </div>
            <div className="form-control md:col-span-2">
              <label className="label">
                <span className="label-text font-medium">Nama lengkap</span>
              </label>
              <input
                name="nama"
                type="text"
                defaultValue={data?.nama || ""}
                disabled={!canEditField("nama") || readOnly}
                className="input input-bordered"
              />
            </div>
            <div className="form-control">
              <label className="label">
                <span className="label-text font-medium">NIP lama</span>
              </label>
              <input
                name="nip_lama"
                type="text"
                defaultValue={data?.nip_lama || ""}
                disabled={!canEditField("nip_lama") || readOnly}
                className="input input-bordered"
              />
            </div>
            <div className="form-control md:col-span-2">
              <label className="label">
                <span className="label-text font-medium">Tempat &amp; tanggal lahir</span>
              </label>
              <input
                name="tempat_tanggal_lahir"
                type="text"
                defaultValue={data?.tempat_tanggal_lahir || ""}
                disabled={!canEditField("tempat_tanggal_lahir") || readOnly}
                className="input input-bordered"
              />
            </div>
            <div className="form-control">
              <label className="label">
                <span className="label-text font-medium">Jenis kelamin</span>
              </label>
              <input
                name="jenis_kelamin"
                type="text"
                defaultValue={data?.jenis_kelamin || ""}
                disabled={!canEditField("jenis_kelamin") || readOnly}
                className="input input-bordered"
              />
            </div>
            <div className="form-control">
              <label className="label">
                <span className="label-text font-medium">Agama</span>
              </label>
              <input
                name="agama"
                type="text"
                defaultValue={data?.agama || ""}
                disabled={!canEditField("agama") || readOnly}
                className="input input-bordered"
              />
            </div>
            <div className="form-control md:col-span-2">
              <label className="label">
                <span className="label-text font-medium">Jenis pegawai</span>
              </label>
              <input
                name="jenis_pegawai"
                type="text"
                defaultValue={data?.jenis_pegawai || ""}
                disabled={!canEditField("jenis_pegawai") || readOnly}
                className="input input-bordered"
              />
            </div>
          </div>
        </fieldset>

        <fieldset className="fieldset mb-4 border border-base-300 rounded-lg p-6 bg-base-100">
          <legend className="fieldset-legend text-lg font-bold px-2">Jabatan &amp; kepangkatan</legend>
          <div className="grid md:grid-cols-2 gap-6 mt-4">
            <div className="form-control md:col-span-2">
              <label className="label">
                <span className="label-text font-medium">Jabatan</span>
              </label>
              <input
                name="jabatan"
                type="text"
                defaultValue={data?.jabatan || ""}
                disabled={!canEditField("jabatan") || readOnly}
                className="input input-bordered"
              />
            </div>
            <div className="form-control md:col-span-2">
              <label className="label">
                <span className="label-text font-medium">Unit kerja</span>
              </label>
              <input
                name="unit_kerja"
                type="text"
                defaultValue={data?.unit_kerja || ""}
                disabled={!canEditField("unit_kerja") || readOnly}
                className="input input-bordered"
              />
            </div>
            <div className="form-control md:col-span-2">
              <label className="label">
                <span className="label-text font-medium">Satker induk</span>
              </label>
              <input
                name="satker_induk"
                type="text"
                defaultValue={data?.satker_induk || ""}
                disabled={!canEditField("satker_induk") || readOnly}
                className="input input-bordered"
              />
            </div>
            <div className="form-control">
              <label className="label">
                <span className="label-text font-medium">Pangkat / golongan</span>
              </label>
              <input
                name="pangkat_golongan"
                type="text"
                defaultValue={data?.pangkat_golongan || ""}
                disabled={!canEditField("pangkat_golongan") || readOnly}
                className="input input-bordered"
              />
            </div>
            <div className="form-control">
              <label className="label">
                <span className="label-text font-medium">Pendidikan terakhir</span>
              </label>
              <input
                name="pendidikan_terakhir"
                type="text"
                defaultValue={data?.pendidikan_terakhir || ""}
                disabled={!canEditField("pendidikan_terakhir") || readOnly}
                className="input input-bordered"
              />
            </div>
          </div>
        </fieldset>

        <fieldset className="fieldset mb-6 border border-base-300 rounded-lg p-6 bg-base-100">
          <legend className="fieldset-legend text-lg font-bold px-2">Wilayah &amp; status</legend>
          <div className="grid md:grid-cols-2 gap-6 mt-4">
            <div className="form-control md:col-span-2">
              <label className="label">
                <span className="label-text font-medium">Source unit (wilayah data)</span>
              </label>
              <select
                name="source_unit_slug"
                className="select select-bordered w-full"
                defaultValue={data?.source_unit_slug || ""}
                disabled={!canEditField("source_unit_slug") || readOnly}
              >
                {slugOptions.map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
                {data?.source_unit_slug && !slugOptions.includes(data.source_unit_slug) ? (
                  <option value={data.source_unit_slug}>{data.source_unit_slug}</option>
                ) : null}
              </select>
              <p className="text-xs opacity-70 mt-1">Nilai harus ada di master `source_units`. Admin Kanwil: {CANONICAL_KANWIL_INDUK_NAME}</p>
            </div>
            <div className="form-control">
              <label className="label cursor-pointer justify-start gap-3">
                <input
                  type="checkbox"
                  name="is_active"
                  value="1"
                  className="checkbox checkbox-primary"
                  defaultChecked={Boolean(data?.is_active)}
                  disabled={!canEditField("is_active") || readOnly}
                />
                <span className="label-text">Tandai aktif (flag basis data)</span>
              </label>
            </div>
          </div>
        </fieldset>

        <div className="flex flex-wrap items-center justify-between gap-4 mt-6 p-4 bg-base-100 rounded-lg border border-base-300 shadow-lg">
          <div className="text-sm opacity-70 flex flex-wrap gap-2">
            {getRole() === "admin_kanwil" && !lockedByKanwilAdmin && (
              <span className="badge badge-info">Admin Kanwil — hanya baris Kantor Wilayah</span>
            )}
            {hasPermission("pegawai.edit_all") && <span className="badge badge-primary">Edit lengkap</span>}
            {hasPermission("pegawai.edit") && !hasPermission("pegawai.edit_all") && (
              <span className="badge badge-secondary">Edit terbatas</span>
            )}
            {readOnly && <span className="badge badge-warning">Read only</span>}
          </div>
          <div className="flex gap-3">
            <a className="btn btn-outline" href="/employees">
              Batal
            </a>
            <button type="submit" className="btn btn-primary" disabled={readOnly || saving}>
              {saving ? (
                <>
                  <span className="loading loading-spinner loading-sm"></span>
                  Menyimpan…
                </>
              ) : (
                "Simpan perubahan"
              )}
            </button>
          </div>
        </div>
      </form>

      <dialog ref={successModalRef} className="modal">
        <div className="modal-box">
          <h3 className="font-bold text-lg text-success">Berhasil</h3>
          <p className="py-4">{modalMessage}</p>
          <div className="modal-action">
            <button type="button" className="btn btn-success" onClick={handleSuccessModalClose}>
              OK
            </button>
          </div>
        </div>
        <form method="dialog" className="modal-backdrop">
          <button type="submit" onClick={handleSuccessModalClose}>
            close
          </button>
        </form>
      </dialog>

      <dialog ref={errorModalRef} className="modal">
        <div className="modal-box">
          <h3 className="font-bold text-lg text-error">Gagal</h3>
          <p className="py-4">{modalMessage}</p>
          <div className="modal-action">
            <button type="button" className="btn btn-error" onClick={handleErrorModalClose}>
              Tutup
            </button>
          </div>
        </div>
        <form method="dialog" className="modal-backdrop">
          <button type="submit" onClick={handleErrorModalClose}>
            close
          </button>
        </form>
      </dialog>
    </div>
  );
}
