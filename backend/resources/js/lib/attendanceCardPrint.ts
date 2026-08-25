import type { QrCodeData, CardLayout, CardElementKey } from '@/types/attendance';
import { cardTemplateApi } from '@/services/attendance';

export interface CardPrintOptions {
    /** Judul window cetak, mis. "Kartu Siswa - X A" */
    title: string;
    /** Nama sekolah untuk header kartu */
    schoolName?: string | null;
    /** URL/path logo sekolah (tenants.logo); dilewati bila kosong */
    schoolLogo?: string | null;
    /** Layout kustom dari Template Editor (Fase 5); tanpa ini pakai layout fixed Fase 2a */
    layout?: CardLayout | null;
}

const CARD_WIDTH_MM = 85.6;
const CARD_HEIGHT_MM = 54;

/**
 * Cetak kartu ID: layout fixed Fase 2a (logo + nama sekolah di header, QR
 * di kiri, identitas di kanan) kecuali `options.layout` diisi (Fase 5,
 * Template Editor) — dalam hal itu tiap elemen ditempatkan absolut sesuai
 * persen yang tersimpan, di atas dimensi kartu CR80 (85.6mm × 54mm).
 *
 * Dulu memakai window.open('', '_blank') + printWindow.print() — itu
 * membuka TAB BARU yang independen dari tab aplikasi. Selama dialog cetak
 * di tab itu belum ditutup/dibatalkan, tab aplikasi ikut tidak merespons
 * klik (kuirk Chromium: dialog cetak lintas-tab bisa mengunci seluruh
 * jendela browser, bukan cuma tab yang memicunya). Sekarang dicetak lewat
 * <iframe> tersembunyi yang ditempel ke halaman yang sama — dialog cetaknya
 * tetap terikat ke tab aplikasi sendiri (sama seperti Ctrl+P biasa), jadi
 * tidak ada tab lain yang bisa "lupa ditutup".
 *
 * Mengembalikan false bila iframe gagal dibuat (pemanggil menampilkan toast).
 */
export function printAttendanceCards(people: QrCodeData[], options: CardPrintOptions): boolean {
    const esc = (value: string | null | undefined): string =>
        (value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const cardsHtml = options.layout
        ? people.map((person) => buildCustomCard(person, options, options.layout!)).join('')
        : people.map((person) => buildFixedCard(person, options, esc)).join('');

    const extraStyle = options.layout ? '' : FIXED_CARD_STYLE;

    const html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>${esc(options.title)}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; padding: 10mm; }
    .grid { display: flex; flex-wrap: wrap; gap: 6mm; }
    .card {
        width: ${CARD_WIDTH_MM}mm;
        height: ${CARD_HEIGHT_MM}mm;
        border: 0.4mm solid #94a3b8;
        border-radius: 3mm;
        overflow: hidden;
        page-break-inside: avoid;
        break-inside: avoid;
        position: relative;
        background: #ffffff;
    }
    ${extraStyle}
    @media print {
        body { padding: 6mm; }
        .card { border-color: #64748b; }
    }
</style>
</head>
<body>
    <div class="grid">${cardsHtml}</div>
</body>
</html>`;

    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const frameWindow = iframe.contentWindow;
    const frameDoc = frameWindow?.document;
    if (!frameWindow || !frameDoc) {
        document.body.removeChild(iframe);
        return false;
    }

    let cleaned = false;
    const cleanup = () => {
        if (cleaned) return;
        cleaned = true;
        if (iframe.parentNode) {
            document.body.removeChild(iframe);
        }
    };

    frameDoc.open();
    frameDoc.write(html);
    frameDoc.close();

    frameWindow.addEventListener('afterprint', cleanup);
    // Sebagian browser tidak selalu memicu 'afterprint' (mis. dibatalkan
    // lewat cara tertentu) — bersihkan iframe selambat-lambatnya semenit
    // kemudian supaya tidak menumpuk di DOM.
    setTimeout(cleanup, 60_000);

    frameWindow.focus();
    setTimeout(() => {
        frameWindow.print();
    }, 300);

    return true;
}

/**
 * Ambil layout aktif tenant untuk jenis kartu ini (kustom bila pernah
 * disimpan lewat Template Editor, default bila belum), lalu cetak. Gagal
 * mengambil layout (mis. offline) diam-diam jatuh ke layout fixed — jangan
 * sampai fitur cetak yang sudah jalan sejak Fase 2a ikut rusak karenanya.
 */
export async function printAttendanceCardsWithTemplate(
    type: 'student' | 'teacher',
    people: QrCodeData[],
    options: Omit<CardPrintOptions, 'layout'>
): Promise<boolean> {
    let layout: CardLayout | null = null;
    try {
        const response = await cardTemplateApi.get(type);
        layout = response.data.data?.layout ?? null;
    } catch {
        layout = null;
    }

    return printAttendanceCards(people, { ...options, layout });
}

function buildFixedCard(
    person: QrCodeData,
    options: CardPrintOptions,
    esc: (value: string | null | undefined) => string
): string {
    const idNumber = person.nis ?? person.nip ?? '';
    const subLine = person.classroom ?? person.employment_status_label ?? '';
    const logoHtml = options.schoolLogo
        ? `<img class="card-logo" src="${esc(options.schoolLogo)}" alt="" />`
        : '';
    const photoHtml = person.photo_url
        ? `<img class="photo" src="${esc(person.photo_url)}" alt="" />`
        : '';

    return `
        <div class="card">
            <div class="card-header">
                ${logoHtml}
                <div class="school-name">${esc(options.schoolName) || 'Kartu Identitas'}</div>
            </div>
            <div class="card-body">
                ${photoHtml}
                <img class="qr" src="${person.qr_code}" alt="QR ${esc(person.name)}" />
                <div class="identity">
                    <div class="name">${esc(person.name)}</div>
                    ${idNumber ? `<div class="id-number">${esc(idNumber)}</div>` : ''}
                    ${subLine ? `<div class="sub-line">${esc(subLine)}</div>` : ''}
                </div>
            </div>
        </div>`;
}

const FIXED_CARD_STYLE = `
    .card { display: flex; flex-direction: column; }
    .card-header {
        display: flex;
        align-items: center;
        gap: 3mm;
        padding: 2.5mm 4mm;
        background: #1e3a5f;
        color: #ffffff;
        min-height: 11mm;
    }
    .card-logo { height: 7mm; width: 7mm; object-fit: contain; background: #ffffff; border-radius: 1mm; padding: 0.5mm; }
    .school-name {
        font-size: 3.4mm;
        font-weight: bold;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.2mm;
        overflow: hidden;
    }
    .card-body {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 3mm;
        padding: 3mm 4mm;
    }
    .photo { width: 18mm; height: 18mm; flex-shrink: 0; object-fit: cover; border-radius: 1.5mm; border: 0.3mm solid #cbd5e1; }
    .qr { width: 26mm; height: 26mm; flex-shrink: 0; }
    .identity { min-width: 0; }
    .name { font-size: 4.2mm; font-weight: bold; line-height: 1.25; word-break: break-word; }
    .id-number { font-size: 3.6mm; color: #334155; margin-top: 1.5mm; }
    .sub-line { font-size: 3.2mm; color: #64748b; margin-top: 1mm; }
`;

function buildCustomCard(person: QrCodeData, options: CardPrintOptions, layout: CardLayout): string {
    const esc = (value: string | null | undefined): string =>
        (value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const idNumber = person.nis ?? person.nip ?? '';
    const subLine = person.classroom ?? person.employment_status_label ?? '';

    const positioned = (key: CardElementKey, inner: string, extraStyle = ''): string => {
        const el = layout.elements[key];
        if (!el) return '';
        const style = [
            'position:absolute',
            `left:${(el.x / 100) * CARD_WIDTH_MM}mm`,
            `top:${(el.y / 100) * CARD_HEIGHT_MM}mm`,
            `width:${(el.width / 100) * CARD_WIDTH_MM}mm`,
            `height:${(el.height / 100) * CARD_HEIGHT_MM}mm`,
            el.fontSize ? `font-size:${el.fontSize}mm` : '',
            extraStyle,
        ].filter(Boolean).join(';');
        return `<div style="${style}">${inner}</div>`;
    };

    const headerHeight = Math.max(
        layout.elements.logo ? layout.elements.logo.y + layout.elements.logo.height : 0,
        layout.elements.schoolName ? layout.elements.schoolName.y + layout.elements.schoolName.height : 0
    );

    return `
        <div class="card" style="background:${esc(layout.cardBackground)}">
            <div style="position:absolute;left:0;top:0;width:100%;height:${headerHeight}%;background:${esc(layout.headerBackground)}"></div>
            ${options.schoolLogo ? positioned('logo', `<img src="${esc(options.schoolLogo)}" alt="" style="width:100%;height:100%;object-fit:contain;background:#ffffff;border-radius:1mm" />`) : ''}
            ${positioned('schoolName', esc(options.schoolName) || 'Kartu Identitas', 'color:#ffffff;font-weight:bold;text-transform:uppercase;line-height:1.2;overflow:hidden')}
            ${person.photo_url ? positioned('photo', `<img src="${esc(person.photo_url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:1.5mm;border:0.3mm solid #cbd5e1" />`) : ''}
            ${positioned('qr', `<img src="${person.qr_code}" alt="QR ${esc(person.name)}" style="width:100%;height:100%" />`)}
            ${positioned('name', esc(person.name), 'font-weight:bold;line-height:1.25;word-break:break-word')}
            ${idNumber ? positioned('idNumber', esc(idNumber), 'color:#334155') : ''}
            ${subLine ? positioned('subLine', esc(subLine), 'color:#64748b') : ''}
        </div>`;
}
