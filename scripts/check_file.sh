if [[ -f "/ssh/key.pub" && -s "/ssh/key.pub" && -f "/certs/self_public" && -f "/certs/self_private" ]]; then
    exit 0;
else
    exit 1;
fi
