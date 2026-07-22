import { useEffect, useRef, useCallback, useState } from 'react';
import { Html5Qrcode } from 'html5-qrcode';
import { IoClose } from 'react-icons/io5';

const BarcodeScanner = ({ onScan, onClose }) => {
    const scannerRef = useRef(null);
    const startedRef = useRef(false);
    const [cameraError, setCameraError] = useState('');

    const stopScanner = useCallback(async () => {
        if (scannerRef.current && startedRef.current) {
            try {
                await scannerRef.current.stop();
            } catch {
                // ya detenido
            }
            startedRef.current = false;
        }
    }, []);

    useEffect(() => {
        let cancelled = false;
        const scanner = new Html5Qrcode('barcode-reader');
        scannerRef.current = scanner;

        const startScanner = async () => {
            try {
                await scanner.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: { width: 280, height: 120 },
                        aspectRatio: 1.5,
                    },
                    (decodedText) => {
                        scanner.stop().catch(() => {});
                        startedRef.current = false;
                        if (typeof onScan === 'function') {
                            onScan(decodedText);
                        }
                    },
                    () => {}
                );
                if (!cancelled) {
                    startedRef.current = true;
                }
            } catch (err) {
                if (!cancelled) {
                    const msg = err?.message || err?.toString() || 'No se pudo acceder a la cámara';
                    setCameraError(msg);
                }
            }
        };

        startScanner();

        return () => {
            cancelled = true;
            if (scannerRef.current && startedRef.current) {
                scannerRef.current.stop().catch(() => {});
            }
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const handleClose = async () => {
        await stopScanner();
        if (typeof onClose === 'function') {
            onClose();
        }
    };

    return (
        <div style={{
            position: 'fixed',
            inset: 0,
            zIndex: 3000,
            backgroundColor: '#000',
            display: 'flex',
            flexDirection: 'column',
        }}>
            <div style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                padding: '12px 16px',
                backgroundColor: '#1976d2',
                color: '#fff',
                fontSize: '15px',
                fontWeight: '600',
                flexShrink: 0,
            }}>
                <span>Escanear código de barras</span>
                <button
                    onClick={handleClose}
                    style={{
                        background: 'none',
                        border: 'none',
                        color: '#fff',
                        cursor: 'pointer',
                        padding: '4px',
                    }}
                >
                    <IoClose size={24} />
                </button>
            </div>
            {cameraError ? (
                <div style={{
                    flex: 1,
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: '32px',
                    color: '#fff',
                    gap: '16px',
                }}>
                    <div style={{ fontSize: '15px', textAlign: 'center' }}>
                        No se pudo acceder a la cámara
                    </div>
                    <div style={{ fontSize: '13px', color: '#aaa', textAlign: 'center' }}>
                        {cameraError}
                    </div>
                    <button
                        onClick={handleClose}
                        style={{
                            marginTop: '8px',
                            padding: '10px 24px',
                            border: 'none',
                            borderRadius: '6px',
                            backgroundColor: '#1976d2',
                            color: '#fff',
                            fontSize: '14px',
                            fontWeight: '500',
                            cursor: 'pointer',
                        }}
                    >
                        Cerrar
                    </button>
                </div>
            ) : (
                <div style={{ flex: 1, position: 'relative' }}>
                    <div id="barcode-reader" style={{ width: '100%', height: '100%' }} />
                </div>
            )}
        </div>
    );
};

export default BarcodeScanner;
