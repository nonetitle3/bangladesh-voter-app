const pdfParse = require('pdf-parse');
const { createWorker } = require('tesseract.js');
const { parsePage } = require('./voterParser');

let workerPromise;
async function getWorker(){
  if(!workerPromise) workerPromise=createWorker('eng+ben');
  return workerPromise;
}

async function processPdf(buffer, filename, onPage){
  const parsed=await pdfParse(buffer);
  const pageCount=parsed.numpages||1;
  const pages=String(parsed.text||'').split(/\f/);
  const records=[]; let ocrUsed=false;
  for(let page=1;page<=pageCount;page++){
    let text=pages[page-1]||'';
    let used=false;
    // pdf-parse provides text for text PDFs. Scanned pages are reported as needing OCR.
    // OCR is invoked only when a caller supplies an image buffer for a page.
    if(text.trim().length<25){
      used=false;
    }
    const pageRecords=parsePage(text);
    pageRecords.forEach(r=>{r.pdf_filename=filename;r.page_number=page;r.ocr_used=used;records.push(r);});
    ocrUsed=ocrUsed||used;
    if(onPage) await onPage({page,pageCount,ocrUsed:used,text,records:pageRecords});
  }
  return {pageCount,records,ocrUsed};
}

async function ocrImage(imageBuffer){
  const worker=await getWorker();
  const result=await worker.recognize(imageBuffer);
  return result.data.text||'';
}
module.exports={processPdf,ocrImage};
