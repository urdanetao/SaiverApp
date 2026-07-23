import { useState, useEffect, useCallback, useRef } from 'react';
import { IoSearchOutline, IoInformationCircleOutline, IoScanOutline } from 'react-icons/io5';
import { CoreWindow, CoreVSep, CoreSuggest, BarcodeScanner } from '../../components';
import { ENTRY_MODE } from '../../util/constants';
import { setBackHandler, clearBackHandler, isRunningInWebView } from '../../util/util';
import useLazyFetch from '../../hooks/useLazyFetch/useLazyFetch';

const Consultor = ({ onBack }) => {
    const { fetchData } = useLazyFetch();
    const suggestRef = useRef(null);
    const [searchId, setSearchId] = useState('');
    const [allProducts, setAllProducts] = useState([]);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [error, setError] = useState('');
    const [showScanner, setShowScanner] = useState(false);

    useEffect(() => {
        (async () => {
            try {
                const response = await fetchData('saintProductosLoad', { id: '' });
                if (response?.status) {
                    const data = Array.isArray(response.data) ? response.data : [response.data];
                    setAllProducts(data);
                }
            } catch {
                // Ignorado al precargar
            }
        })();
        setBackHandler(() => {
            if (typeof onBack === 'function') {
                onBack();
            }
        });
        return () => clearBackHandler();
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const handleSearch = useCallback(async (id) => {
        if (!id || id.trim() === '') {
            return;
        }

        setError('');
        try {
            const response = await fetchData('saintProductosLoad', { id: id.trim() });
            if (response?.status) {
                const data = Array.isArray(response.data) ? response.data : [response.data];
                setSelectedProduct(data[0]);
            } else {
                setSelectedProduct(null);
                setError(response?.message || 'Producto no encontrado');
            }
        } catch {
            setSelectedProduct(null);
        } finally {
            setSearchId('');
            suggestRef.current?.skipNextBlurCommit();
            if (document.activeElement) document.activeElement.blur();
        }
    }, [fetchData]);

    const handleSelectProduct = useCallback((value, option) => {
        if (option) {
            setSelectedProduct(option);
        }
        setError('');
        setSearchId('');
        setTimeout(() => {
            if (document.activeElement) document.activeElement.blur();
        }, 100);
    }, []);

    const handleScan = useCallback((code) => {
        setShowScanner(false);
        handleSearch(code.toUpperCase());
    }, [handleSearch]);

    useEffect(() => {
        window.onBarcodeScanned = (code) => {
            if (code) {
                handleScan(code);
            }
        };
        return () => { window.onBarcodeScanned = null; };
    }, [handleScan]);

    const containerStyles = {
        display: 'flex',
        flexDirection: 'column',
        height: '100%',
        backgroundColor: '#f8fafc',
    };

    const headerStyles = {
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        padding: '8px 16px',
        backgroundColor: '#fff',
        borderBottom: '1px solid #e2e8f0',
        boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
        flexShrink: 0,
    };

    const titleStyles = {
        fontSize: '15px',
        fontWeight: '700',
        color: '#1e293b',
        margin: 0,
    };

    const priceCardStyles = {
        backgroundColor: '#fff',
        borderRadius: '10px',
        padding: '16px',
        marginBottom: '12px',
        boxShadow: '0 2px 8px rgba(0,0,0,0.06)',
        border: '1px solid #e8edf2',
    };

    const priceRowStyles = {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '8px 0',
        borderBottom: '1px solid #f0f4f8',
    };

    const priceLabelStyles = {
        fontSize: '13px',
        fontWeight: '600',
        color: '#475569',
    };

    const priceValueStyles = {
        fontSize: '14px',
        fontWeight: '700',
        color: '#1e293b',
    };

    const sectionTitleStyles = {
        fontSize: '11px',
        fontWeight: '700',
        textTransform: 'uppercase',
        letterSpacing: '0.05em',
        color: '#64748b',
        marginBottom: '8px',
        marginTop: '12px',
    };

    const formatPrice = (value) => {
        const num = parseFloat(value);
        if (isNaN(num)) return '0.00';
        return num.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const renderPrices = (product) => {
        if (!product) return null;

        return (
            <>
                <div style={sectionTitleStyles}>Precios en Dolares + IGTF</div>
                <div style={priceCardStyles}>
                    <div style={priceRowStyles}>
                        <span style={priceLabelStyles}>Precio 1</span>
                        <span style={{ ...priceValueStyles, color: '#2e7d32' }}>$ {formatPrice(product.precio1usdigtf)}</span>
                    </div>
                    <div style={priceRowStyles}>
                        <span style={priceLabelStyles}>Precio 2</span>
                        <span style={{ ...priceValueStyles, color: '#2e7d32' }}>$ {formatPrice(product.precio2usdigtf)}</span>
                    </div>
                    <div style={{ ...priceRowStyles, borderBottom: 'none' }}>
                        <span style={priceLabelStyles}>Precio 3</span>
                        <span style={{ ...priceValueStyles, color: '#2e7d32' }}>$ {formatPrice(product.precio3usdigtf)}</span>
                    </div>
                </div>

                <div style={sectionTitleStyles}>Precios en Pesos Colombianos + IGTF</div>
                <div style={priceCardStyles}>
                    <div style={priceRowStyles}>
                        <span style={priceLabelStyles}>Precio 1</span>
                        <span style={{ ...priceValueStyles, color: '#6a1b9a' }}>$ {formatPrice(product.precio1coligtf)}</span>
                    </div>
                    <div style={priceRowStyles}>
                        <span style={priceLabelStyles}>Precio 2</span>
                        <span style={{ ...priceValueStyles, color: '#6a1b9a' }}>$ {formatPrice(product.precio2coligtf)}</span>
                    </div>
                    <div style={{ ...priceRowStyles, borderBottom: 'none' }}>
                        <span style={priceLabelStyles}>Precio 3</span>
                        <span style={{ ...priceValueStyles, color: '#6a1b9a' }}>$ {formatPrice(product.precio3coligtf)}</span>
                    </div>
                </div>
            </>
        );
    };

    return (
        <>
            <div style={containerStyles}>
                <div style={headerStyles}>
                    <IoSearchOutline size={22} color="#1976d2" />
                    <h2 style={titleStyles}>Consultor de Precios</h2>
                </div>

                <div style={{ flex: 1, overflowY: 'auto', padding: '16px' }}>
                    <CoreWindow
                        icon={<IoSearchOutline size={20} color="#fff" />}
                        title="Buscar Producto"
                    >
                    <div style={{ display: 'flex', gap: '8px', alignItems: 'flex-end' }}>
                        <div style={{ flex: 1, minWidth: 0 }}>
                                <CoreSuggest
                                    ref={suggestRef}
                                    label="Producto"
                                    value={searchId}
                                    onChange={(e) => {
                                        setSearchId(e.target.value);
                                    }}
                                    onSelect={handleSelectProduct}
                                    onEnter={(e, { inputValue }) => {
                                        if (inputValue) {
                                            handleSearch(inputValue);
                                        }
                                    }}
                                    options={allProducts.map(r => ({
                                        ...r,
                                        id: r.codprod,
                                        text1: r.descrip,
                                    }))}
                                    fieldId="id"
                                    displayField="text1"
                                    listDisplayField="text1"
                                    filterFields={['codprod', 'codbarra', 'descrip']}
                                    width="100%"
                                    listDisplayWidth="calc(100% + 44px)"
                                    showMaxItems={7}
                                    ignoreFormState={true}
                                    entryMode={ENTRY_MODE.UPPER}
                                />
                            </div>
                            <button
                                onClick={() => {
                                    if (isRunningInWebView()) {
                                        Android.scanBarcode();
                                    } else {
                                        setShowScanner(true);
                                    }
                                }}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    width: '36px',
                                    height: '36px',
                                    minWidth: '36px',
                                    border: '1px solid #ccc',
                                    borderRadius: '4px',
                                    backgroundColor: '#f8fafc',
                                    cursor: 'pointer',
                                    color: '#1976d2',
                                }}
                            >
                                <IoScanOutline size={20} />
                            </button>
                        </div>

                        {error && (
                            <>
                                <CoreVSep size={8} />
                                <div style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '6px',
                                    padding: '8px 12px',
                                    backgroundColor: '#fef2f2',
                                    borderRadius: '6px',
                                    border: '1px solid #fecaca',
                                }}>
                                    <IoInformationCircleOutline size={16} color="#dc2626" />
                                    <span style={{ fontSize: '12px', color: '#dc2626' }}>{error}</span>
                                </div>
                            </>
                        )}

                        {selectedProduct && (
                            <>
                                <CoreVSep size={12} />
                                <div style={{
                                    padding: '10px 12px',
                                    backgroundColor: '#f0fdf4',
                                    borderRadius: '6px',
                                    border: '1px solid #bbf7d0',
                                    marginBottom: '12px',
                                }}>
                                    <div style={{ fontSize: '12px', fontWeight: '600', color: '#166534', marginBottom: '4px' }}>
                                        {selectedProduct.codprod}
                                    </div>
                                    <div style={{ fontSize: '13px', color: '#15803d' }}>
                                        {selectedProduct.descrip}
                                    </div>
                                    {selectedProduct.codbarra && (
                                        <div style={{ fontSize: '11px', color: '#64748b', marginTop: '4px' }}>
                                            Barcode: {selectedProduct.codbarra}
                                        </div>
                                    )}
                                </div>
                                {renderPrices(selectedProduct)}
                            </>
                        )}
                    </CoreWindow>
                </div>
            </div>

            {showScanner && (
                <BarcodeScanner
                    onScan={handleScan}
                    onClose={() => setShowScanner(false)}
                />
            )}
        </>
    );
};

export default Consultor;
